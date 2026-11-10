<?php

declare(strict_types=1);

namespace Modules\Core\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\Exceptions\PageNotFoundException;
use Modules\Core\Install\Installer;
use Modules\Core\Install\InstallException;
use Modules\Core\Install\InstallOptions;
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
 */
final class WebInstaller extends BaseController
{
    /** Form fields read from the POST (never the whole POST). */
    private const FIELDS = ['environment', 'baseURL', 'dbDriver', 'dbHost', 'dbPort', 'dbName', 'dbUser', 'dbPassword', 'dbPrefix', 'adminUsername', 'adminEmail', 'adminPassword', 'adminPasswordConfirm', 'demo'];

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
        );

        $installer = new Installer(service('installstate'));
        $errors    = $installer->validate($options);

        if (! hash_equals($options->adminPassword, is_string($post['adminPasswordConfirm'] ?? null) ? $post['adminPasswordConfirm'] : '')) {
            $errors['adminPasswordConfirm'] = lang('Install.invalid.passwordConfirm');
        }

        if ($errors !== []) {
            return $this->form($options, $errors);
        }

        try {
            $result = $installer->install($options);
        } catch (InstallException $e) {
            return $this->form($options, $e->errors, $e->getMessage());
        }

        return view('Modules\Core\Views\install\done', [
            'base'     => $this->basePath(),
            'result'   => $result,
            'envPath'  => service('installstate')->envPath(),
            'loginUrl' => rtrim($options->baseURL !== '' ? $options->baseURL : (string) config('App')->baseURL, '/') . '/login',
            'username' => $options->adminUsername,
        ]);
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
        ]);
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
