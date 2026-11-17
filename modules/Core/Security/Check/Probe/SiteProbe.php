<?php

declare(strict_types=1);

namespace Modules\Core\Security\Check\Probe;

use Closure;
use Config\App;
use Modules\Core\Config\ProductionCheck;
use Modules\Core\Security\Check\SecurityCheck;
use Modules\Core\Security\Check\SecurityReport;
use RuntimeException;
use Throwable;

/**
 * Group `probe` (`security:check --probe=<url>`, or the "run external probe" button with
 * re-authentication): requests to the site itself through ProbeClient. It checks that the
 * security headers are sent, that `/.env`, `writable/`, `modules/`, `app/` and `vendor/` are not
 * served, that a PHP file in `uploads/` is not executed (a harmless probe file, removed
 * afterwards), that an external `return` URL is not followed, that the login is throttled, that
 * plain HTTP redirects to HTTPS and that the `progress/*` endpoints return no secrets.
 */
final class SiteProbe implements SecurityCheck
{
    private const GROUP = 'probe';

    /** Paths that must never be served (relative to the site root). */
    public const HIDDEN = ['.env', 'writable/install.lock', 'writable/logs/', 'writable/packages/', 'writable/backups/', 'modules/Core/Config/Module.php', 'app/Config/App.php', 'vendor/autoload.php', 'composer.json', '.git/config'];

    /** Keys that must not appear in a `progress/*` answer. */
    private const SECRET_KEYS = '/"[^"]*(?:pass|secret|token|key|hash|email|path|file)[^"]*"\s*:/i';

    private readonly string $base;

    /**
     * @param list<string>|null    $progressPaths  `progress/*` route patterns (default: the router's)
     * @param (Closure(): int)|null $loginAttempts most attempts before the login must be throttled
     */
    public function __construct(
        string $url,
        private readonly ?ProbeClient $client = null,
        private readonly ?ProductionCheck $config = null,
        private readonly ?App $app = null,
        private readonly string $public = FCPATH,
        private readonly ?array $progressPaths = null,
        private readonly ?Closure $loginAttempts = null,
    ) {
        $this->base = rtrim($url, '/') . '/';
    }

    /**
     * The hosts the probe may call: the base URL host and `allowedHostnames`.
     *
     * @return list<string>
     */
    public static function siteHosts(?App $app = null): array
    {
        $app ??= config(App::class);
        $hosts = [strtolower((string) parse_url($app->baseURL, PHP_URL_HOST))];

        foreach ($app->allowedHostnames as $host) {
            $hosts[] = strtolower(trim($host));
        }

        return array_values(array_unique(array_filter($hosts, static fn (string $host): bool => $host !== '')));
    }

    public function groups(): array
    {
        return [self::GROUP];
    }

    public function run(SecurityReport $report): void
    {
        $config = $this->config ?? config(ProductionCheck::class);
        $client = $this->client ?? new ProbeClient(self::siteHosts($this->app), $config->probeTimeout);

        if (! $client->allows($this->base)) {
            $report->error(self::GROUP, 'probe.target', lang('SecurityCheck.probe.target'), lang('SecurityCheck.probe.refused', [$this->base, implode(', ', self::siteHosts($this->app))]), lang('SecurityCheck.probe.fixTarget'));

            return;
        }

        $steps = [
            'headers'     => fn () => $this->headers($client, $report),
            'hidden'      => fn () => $this->hidden($client, $report),
            'uploads'     => fn () => $this->uploads($client, $report),
            'redirect'    => fn () => $this->openRedirect($client, $report),
            'login'       => fn () => $this->login($client, $report, $config),
            'https'       => fn () => $this->https($client, $report),
            'progress'    => fn () => $this->progress($client, $report),
        ];

        foreach ($steps as $name => $step) {
            try {
                $step();
            } catch (Throwable $e) {
                $report->warning(self::GROUP, 'probe.' . $name, lang('SecurityCheck.probe.' . $name), lang('SecurityCheck.probe.unreachable', [$e->getMessage()]), lang('SecurityCheck.probe.fixUnreachable'));
            }
        }
    }

    private function headers(ProbeClient $client, SecurityReport $report): void
    {
        $response = $client->request('GET', $this->base);
        $missing  = [];
        $expected = ['Content-Security-Policy' => null, 'Strict-Transport-Security' => null, 'X-Content-Type-Options' => 'nosniff', 'X-Frame-Options' => null, 'Referrer-Policy' => null, 'Permissions-Policy' => null];

        foreach ($expected as $name => $value) {
            $sent = $response->header($name);

            if ($sent === '' || ($value !== null && strtolower($sent) !== $value)) {
                $missing[] = $name;
            }
        }

        if (str_contains(strtolower($response->header('Content-Security-Policy')), 'unsafe-')) {
            $missing[] = 'Content-Security-Policy (unsafe-*)';
        }

        if ($missing === []) {
            $report->ok(self::GROUP, 'probe.headers', lang('SecurityCheck.probe.headers'), lang('SecurityCheck.probe.headersOk', [$response->status]));
        } else {
            $report->error(self::GROUP, 'probe.headers', lang('SecurityCheck.probe.headers'), lang('SecurityCheck.probe.headersMissing', [implode(', ', $missing)]), lang('SecurityCheck.probe.fixHeaders'));
        }
    }

    private function hidden(ProbeClient $client, SecurityReport $report): void
    {
        $served = [];

        foreach (self::HIDDEN as $path) {
            if ($client->request('GET', $this->base . $path)->status === 200) {
                $served[] = '/' . $path;
            }
        }

        if ($served === []) {
            $report->ok(self::GROUP, 'probe.hidden', lang('SecurityCheck.probe.hidden'), lang('SecurityCheck.probe.hiddenOk'));
        } else {
            $report->error(self::GROUP, 'probe.hidden', lang('SecurityCheck.probe.hidden'), lang('SecurityCheck.probe.hiddenServed', [implode(', ', $served)]), lang('SecurityCheck.probe.fixHidden'));
        }
    }

    private function uploads(ProbeClient $client, SecurityReport $report): void
    {
        $name   = 'kp-security-probe-' . bin2hex(random_bytes(8)) . '.php';
        $folder = rtrim($this->public, '/') . '/uploads';
        $file   = $folder . '/' . $name;
        $marker = 'kp-probe-' . bin2hex(random_bytes(6));

        if (! is_dir($folder) || @file_put_contents($file, "<?php echo strrev('" . strrev($marker) . "');\n") === false) {
            $report->warning(self::GROUP, 'probe.uploads', lang('SecurityCheck.probe.uploads'), lang('SecurityCheck.probe.uploadsSkipped', [$folder]), lang('SecurityCheck.probe.fixUploadsSkipped'));

            return;
        }

        try {
            $response = $client->request('GET', $this->base . 'uploads/' . $name);
        } finally {
            @unlink($file);
        }

        if ($response->status === 200 && str_contains($response->body, $marker)) {
            $report->error(self::GROUP, 'probe.uploads', lang('SecurityCheck.probe.uploads'), lang('SecurityCheck.probe.uploadsExecuted'), lang('SecurityCheck.probe.fixUploads'));
        } else {
            $report->ok(self::GROUP, 'probe.uploads', lang('SecurityCheck.probe.uploads'), lang('SecurityCheck.probe.uploadsOk', [$response->status]));
        }
    }

    private function openRedirect(ProbeClient $client, SecurityReport $report): void
    {
        $external = 'https://kp-security-probe.invalid/';
        $followed = [];

        foreach (['login?return=', 'backend?return=', '?return='] as $path) {
            $location = $client->request('GET', $this->base . $path . rawurlencode($external))->header('location');

            if ($location !== '' && str_contains(strtolower($location), 'kp-security-probe.invalid')) {
                $followed[] = '/' . $path;
            }
        }

        if ($followed === []) {
            $report->ok(self::GROUP, 'probe.redirect', lang('SecurityCheck.probe.redirect'), lang('SecurityCheck.probe.redirectOk'));
        } else {
            $report->error(self::GROUP, 'probe.redirect', lang('SecurityCheck.probe.redirect'), lang('SecurityCheck.probe.redirectFollowed', [implode(', ', $followed)]), lang('SecurityCheck.probe.fixRedirect'));
        }
    }

    private function login(ProbeClient $client, SecurityReport $report, ProductionCheck $config): void
    {
        if ($config->probeLoginPath === '') {
            return;
        }

        $url      = $this->base . ltrim($config->probeLoginPath, '/');
        $attempts = $this->loginAttempts !== null ? ($this->loginAttempts)() : $config->probeLoginAttempts;
        $email    = 'security-check-' . bin2hex(random_bytes(4)) . '@example.invalid';
        $token    = config(\Config\Security::class)->tokenName;

        for ($i = 1; $i <= $attempts; $i++) {
            $form = $client->request('GET', $url);

            if (preg_match('/name="' . preg_quote((string) $token, '/') . '"\s+value="([^"]+)"/', $form->body, $m) !== 1 && preg_match('/value="([^"]+)"\s+name="' . preg_quote((string) $token, '/') . '"/', $form->body, $m) !== 1) {
                if ($form->status === 429) {
                    break;
                }

                throw new RuntimeException(lang('SecurityCheck.probe.noLoginForm', ['/' . ltrim($config->probeLoginPath, '/')]));
            }

            $response = $client->request('POST', $url, ['Content-Type' => 'application/x-www-form-urlencoded'], http_build_query([$token => html_entity_decode($m[1]), 'email' => $email, 'password' => bin2hex(random_bytes(8))]));

            if ($response->status === 429) {
                $report->ok(self::GROUP, 'probe.login', lang('SecurityCheck.probe.login'), lang('SecurityCheck.probe.loginOk', [$i]));

                return;
            }
        }

        $report->error(self::GROUP, 'probe.login', lang('SecurityCheck.probe.login'), lang('SecurityCheck.probe.loginNotThrottled', [$attempts]), lang('SecurityCheck.probe.fixLogin'));
    }

    private function https(ProbeClient $client, SecurityReport $report): void
    {
        $http = 'http://' . substr($this->base, strlen('https://'));

        try {
            $response = $client->request('GET', $http, [], null, true);
        } catch (RuntimeException) {
            $report->ok(self::GROUP, 'probe.https', lang('SecurityCheck.probe.https'), lang('SecurityCheck.probe.httpsClosed'));

            return;
        }

        $location = $response->header('location');

        if (in_array($response->status, [301, 302, 303, 307, 308], true) && str_starts_with(strtolower($location), 'https://')) {
            $report->ok(self::GROUP, 'probe.https', lang('SecurityCheck.probe.https'), lang('SecurityCheck.probe.httpsOk', [$response->status]));
        } else {
            $report->error(self::GROUP, 'probe.https', lang('SecurityCheck.probe.https'), lang('SecurityCheck.probe.httpsMissing', [$response->status]), lang('SecurityCheck.probe.fixHttps'));
        }
    }

    private function progress(ProbeClient $client, SecurityReport $report): void
    {
        $paths = $this->progressPaths ?? self::routerProgressPaths();

        if ($paths === []) {
            return;
        }

        $leaks = [];

        foreach ($paths as $pattern) {
            $path     = (string) preg_replace('/\([^)]*\)/', bin2hex(random_bytes(6)), $pattern);
            $response = $client->request('GET', $this->base . ltrim($path, '/'));

            if (preg_match(self::SECRET_KEYS, $response->body) === 1) {
                $leaks[] = '/' . ltrim($pattern, '/');
            }
        }

        if ($leaks === []) {
            $report->ok(self::GROUP, 'probe.progress', lang('SecurityCheck.probe.progress'), lang('SecurityCheck.probe.progressOk', [count($paths)]));
        } else {
            $report->error(self::GROUP, 'probe.progress', lang('SecurityCheck.probe.progress'), lang('SecurityCheck.probe.progressLeaks', [implode(', ', $leaks)]), lang('SecurityCheck.probe.fixProgress'));
        }
    }

    /**
     * GET routes under `progress/` (D-27-02).
     *
     * @return list<string>
     */
    public static function routerProgressPaths(): array
    {
        $routes = service('routes');
        $routes->loadRoutes();

        return array_values(array_filter(array_map('strval', array_keys($routes->getRoutes('GET'))), static fn (string $path): bool => str_starts_with(ltrim($path, '/'), 'progress/')));
    }
}
