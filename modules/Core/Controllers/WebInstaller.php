<?php

declare(strict_types=1);

namespace Modules\Core\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;
use Modules\Core\Frontend\AssetManifest;
use Modules\Core\Frontend\AssetPublisher;
use Modules\Core\Install\Installer;
use Modules\Core\Install\InstallException;
use Modules\Core\Install\InstallJob;
use Modules\Core\Install\InstallJobs;
use Modules\Core\Install\InstallOptions;
use Modules\Core\Install\InstallResult;
use Modules\Core\Security\RateLimiter;
use Throwable;

/**
 * The optional web installer (`install`), the same service as `php spark app:install`.
 *
 * - Answers 404 as soon as `.env` or `writable/install.lock` exists, and when the configured
 *   database already has tables.
 * - Warns when the page is not served over HTTPS (in production the request is already
 *   redirected to HTTPS by `forceGlobalSecureRequests`).
 * - When the project root is not writable by the web server (the recommended setup), the
 *   installation still completes and the last page shows the `.env` content to copy.
 * - Standalone page (no theme, no settings: there is no database yet); passwords are never sent
 *   back to the form.
 * - Progress (session 39): when the page's script is running (`progress=1`) and the server can
 *   finish the response first (InstallJob::canRunAfterResponse()), a valid POST starts a job
 *   (InstallJobs) and answers the progress page, which polls `progress/install/<job>` and then
 *   opens `install/result/<job>`; the result page shows the one-time values once. Otherwise
 *   (no JavaScript, `php spark serve`, mod_php) the installation runs inside the request as
 *   before. Only one installation at a time (InstallJobs::lock()). The progress and result
 *   pages answer for an existing job only, until it expires, also once installed.
 */
final class WebInstaller extends BaseController
{
    /** Form fields read from the POST (never the whole POST). */
    private const FIELDS = ['environment', 'baseURL', 'dbDriver', 'dbHost', 'dbPort', 'dbName', 'dbUser', 'dbPassword', 'dbPrefix', 'backendPath', 'adminUsername', 'adminEmail', 'adminPassword', 'adminPasswordConfirm', 'demo'];

    /** Requests per minute and IP address to `progress/install/<job>`. */
    public const PROGRESS_LIMIT = 120;

    public function index(): string
    {
        $this->assertAvailable();
        $this->response->setHeader('Cache-Control', 'no-store');

        return $this->form(new InstallOptions(baseURL: (string) config('App')->baseURL, dbDriver: 'MySQLi', dbName: ''));
    }

    public function install(): string
    {
        $this->assertAvailable();
        $this->response->setHeader('Cache-Control', 'no-store');

        $post    = $this->request->getPost(self::FIELDS);
        $text    = static fn (string $key): string => is_string($post[$key] ?? null) ? trim($post[$key]) : '';
        $options = new InstallOptions(
            environment: $text('environment'),
            baseURL: $text('baseURL'),
            dbDriver: InstallOptions::driver($text('dbDriver')),
            dbHost: $text('dbHost'),
            dbPort: (int) $text('dbPort'),
            dbName: $text('dbName'),
            dbUser: $text('dbUser'),
            dbPassword: is_string($post['dbPassword'] ?? null) ? $post['dbPassword'] : '',
            dbPrefix: $text('dbPrefix'),
            adminUsername: $text('adminUsername'),
            adminEmail: $text('adminEmail'),
            adminPassword: is_string($post['adminPassword'] ?? null) ? $post['adminPassword'] : '',
            demo: ($post['demo'] ?? '') === '1' ? [] : null,
            backendPath: strtolower($text('backendPath')),
        );

        $installer = new Installer(service('installstate'));
        $errors    = $installer->validate($options);

        if (! hash_equals($options->adminPassword, is_string($post['adminPasswordConfirm'] ?? null) ? $post['adminPasswordConfirm'] : '')) {
            $errors['adminPasswordConfirm'] = lang('Install.invalid.passwordConfirm');
        }

        if ($errors !== []) {
            return $this->form($options, $errors);
        }

        $jobs = new InstallJobs(service('installstate'));
        $page = [
            'loginUrl' => rtrim($options->baseURL !== '' ? $options->baseURL : (string) config('App')->baseURL, '/') . '/login',
            'username' => $options->adminUsername,
        ];

        // With the page's script and a server that can answer first: a job with its progress page.
        if ($this->request->getPost('progress') === '1' && InstallJob::canRunAfterResponse()) {
            $id = $jobs->create($this->request->getLocale());

            if (! $jobs->lock($id)) {
                $jobs->delete($id);

                return $this->refuseRunning($options);
            }

            $job = new InstallJob($jobs, $installer);
            InstallJob::afterResponse(static function () use ($job, $id, $options, $page): void {
                $job->run($id, $options, $page);
            });

            return view('Modules\Core\Views\install\progress', [
                'base'      => $this->basePath(),
                'scripts'   => true,
                'progress'  => site_url('progress/install/' . $id),
                'resultUrl' => site_url('install/result/' . $id),
                'job'       => $jobs->get($id) ?? [],
            ]);
        }

        // Fallback: inside the request, as before.
        $owner = bin2hex(random_bytes(16));

        if (! $jobs->lock($owner)) {
            return $this->refuseRunning($options);
        }

        try {
            $result = $installer->install($options);
        } catch (InstallException $e) {
            return $this->form($options, $e->errors, $e->getMessage());
        } finally {
            $jobs->unlock($owner);
        }

        return $this->done($result, $page);
    }

    /**
     * `GET progress/install/<job>`: the progress of a web installation as JSON (status, step,
     * its label, percentage, the log lines and, once finished, the result page). Read from a
     * file, without the database or the session; the job id (128 random bits) is the
     * capability, no secret is returned, and requests are limited per IP address.
     */
    public function progress(string $id): ResponseInterface
    {
        $this->response->setHeader('Cache-Control', 'no-store');

        $wait = (new RateLimiter())->attemptCustom('installProgress', 'ip:' . $this->request->getIPAddress(), self::PROGRESS_LIMIT, MINUTE);

        if ($wait > 0) {
            return $this->response->setStatusCode(429)->setHeader('Retry-After', (string) $wait)->setJSON(['status' => 'limited']);
        }

        $job = (new InstallJobs(service('installstate')))->get($id);

        if ($job === null) {
            return $this->response->setStatusCode(404)->setJSON(['status' => 'unknown']);
        }

        $this->useJobLocale($job);
        $status = (string) ($job['status'] ?? 'queued');
        $lines  = [];

        foreach (is_array($job['log'] ?? null) ? $job['log'] : [] as $line) {
            if (is_array($line) && is_string($line['key'] ?? null)) {
                $lines[] = self::label($line['key'], is_array($line['params'] ?? null) ? $line['params'] : []);
            }
        }

        return $this->response->setJSON([
            'status'  => $status,
            'step'    => (string) ($job['step'] ?? 'queued'),
            'label'   => self::stepLabel($job),
            'percent' => (int) ($job['percent'] ?? 0),
            'log'     => $lines,
            'next'    => in_array($status, ['done', 'failed'], true) ? site_url('install/result/' . $id) : null,
        ]);
    }

    /**
     * `GET install/result/<job>`: the result of a web installation (the one-time values only the
     * first time), its failure, or "still running" (the page reloads itself). 404 for an
     * unknown or expired job.
     */
    public function result(string $id): string
    {
        $jobs = new InstallJobs(service('installstate'));
        $job  = $jobs->get($id);

        if ($job === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        $this->response->setHeader('Cache-Control', 'no-store');
        $status = (string) ($job['status'] ?? 'queued');

        if ($status === 'failed') {
            return view('Modules\Core\Views\install\failed', [
                'base'    => $this->basePath(),
                'step'    => self::stepLabel($job),
                'message' => (string) ($job['message'] ?? ''),
                'errors'  => array_filter(is_array($job['errors'] ?? null) ? $job['errors'] : [], 'is_string'),
            ]);
        }

        if ($status !== 'done') {
            // Still running: the same progress page (it reloads itself without the scripts).
            return view('Modules\Core\Views\install\progress', [
                'base'      => $this->basePath(),
                'scripts'   => $this->scriptsPublished(),
                'progress'  => site_url('progress/install/' . $id),
                'resultUrl' => site_url('install/result/' . $id),
                'job'       => $job,
            ]);
        }

        $data    = is_array($job['result'] ?? null) ? $job['result'] : [];
        $secrets = $jobs->takeSecrets($id);
        $result  = new InstallResult();

        $result->envWritten  = (bool) ($data['envWritten'] ?? false);
        $result->env         = is_string($secrets['env'] ?? null) ? $secrets['env'] : '';
        $result->demoSecrets = is_array($secrets['demo'] ?? null) ? array_map('strval', $secrets['demo']) : [];
        $result->demoLoaded  = array_values(array_map('strval', is_array($data['demoLoaded'] ?? null) ? $data['demoLoaded'] : []));
        $result->demoError   = (string) ($data['demoError'] ?? '');
        $result->cronLine    = (string) ($data['cronLine'] ?? '');

        return $this->done($result, [
            'loginUrl' => (string) ($data['loginUrl'] ?? ''),
            'username' => (string) ($data['username'] ?? ''),
        ], $secrets === null && ($data['oneTime'] ?? false) === true, is_array($data['security'] ?? null) ? $data['security'] : null);
    }

    /**
     * The label of a job's current step (`Install.step.<code>` with its counts and parameters).
     *
     * @param array<string, mixed> $job
     */
    public static function stepLabel(array $job): string
    {
        $params = is_array($job['params'] ?? null) ? $job['params'] : [];

        return self::label('Install.step.' . (string) ($job['step'] ?? 'queued'), ['done' => (int) ($job['done'] ?? 0), 'total' => (int) ($job['total'] ?? 0)] + $params);
    }

    /**
     * A log line: only `Install.*` language keys, with scalar parameters.
     *
     * @param array<array-key, mixed> $params
     */
    private static function label(string $key, array $params): string
    {
        if (preg_match('/\AInstall\.(?:step|progress)\.[A-Za-z]+\z/', $key) !== 1) {
            return '';
        }

        return lang($key, array_filter($params, 'is_scalar'));
    }

    /**
     * @param array<string, mixed> $job
     */
    private function useJobLocale(array $job): void
    {
        $locale = (string) ($job['locale'] ?? '');

        if (in_array($locale, config('App')->supportedLocales, true)) {
            service('language')->setLocale($locale);
        }
    }

    /**
     * @param array{loginUrl: string, username: string}  $page
     * @param array{errors?: mixed, warnings?: mixed}|null $security
     */
    private function done(InstallResult $result, array $page, bool $secretsShown = false, ?array $security = null): string
    {
        return view('Modules\Core\Views\install\done', [
            'base'         => $this->basePath(),
            'result'       => $result,
            'envPath'      => service('installstate')->envPath(),
            'loginUrl'     => $page['loginUrl'],
            'username'     => $page['username'],
            'secretsShown' => $secretsShown,
            'security'     => $security,
        ]);
    }

    /**
     * Another installation holds the lock: the form again, 409.
     */
    private function refuseRunning(InstallOptions $options): string
    {
        $this->response->setStatusCode(409);

        return $this->form($options, [], lang('Install.error.running'));
    }

    /**
     * @param array<string, string> $errors
     */
    private function form(InstallOptions $options, array $errors = [], string $message = ''): string
    {
        $options->dbPassword    = '';
        $options->adminPassword = '';
        $state                  = service('installstate');

        return view('Modules\Core\Views\install\form', [
            'base'         => $this->basePath(),
            'options'      => $options,
            'errors'       => $errors,
            'message'      => $message,
            'secure'       => $this->request->isSecure(),
            'rootWritable' => $state->rootWritable(),
            'envPath'      => $state->envPath(),
            'minPassword'  => (int) config('Auth')->minimumPasswordLength,
            'scripts'      => $this->scriptsPublished(),
        ]);
    }

    /**
     * Whether the Core's scripts are published (the progress page needs them). Before the first
     * `modules:sync` they are not: the installer publishes the Core's assets when it can write
     * `public/assets`; otherwise the pages work without JavaScript (installation in the request).
     */
    private function scriptsPublished(): bool
    {
        if (service('assets')->url('core::js/install/progress.js') !== null) {
            return true;
        }

        try {
            (new AssetPublisher())->publish('core');
        } catch (Throwable $e) {
            log_message('warning', 'The web installer could not publish the Core assets: {message}', ['message' => $e->getMessage()]);

            return false;
        }

        Services::resetSingle('assets');

        return (new AssetManifest())->url('core::js/install/progress.js') !== null;
    }

    /**
     * 404 once configured, or when the configured database already has tables.
     */
    private function assertAvailable(): void
    {
        if (service('installstate')->isConfigured()) {
            throw PageNotFoundException::forPageNotFound();
        }

        try {
            $hasTables = Installer::databaseHasTables();
        } catch (Throwable) {
            $hasTables = false; // No usable database configured yet: the form asks for one.
        }

        if ($hasTables) {
            throw PageNotFoundException::forPageNotFound();
        }
    }

    /**
     * The path of the front controller's folder, for the page's stylesheet ('/' at the root).
     */
    private function basePath(): string
    {
        $path = (string) parse_url((string) config('App')->baseURL, PHP_URL_PATH);

        return rtrim($path, '/') . '/';
    }
}
