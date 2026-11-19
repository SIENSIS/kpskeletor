<?php

declare(strict_types=1);

namespace Modules\Core\Security\Check\Checks;

use Config\App;
use Config\ContentSecurityPolicy as CspConfig;
use Config\Cookie;
use Modules\Core\Routing\BackendPath;
use Modules\Core\Security\Check\EffectivePolicy;
use Modules\Core\Security\Check\SecurityCheck;
use Modules\Core\Security\Check\SecurityReport;

/**
 * Group `configuration`: production environment, no debug output, HTTPS everywhere, secure
 * cookies, a real base URL, an enforced CSP without `unsafe-*`, HSTS and the other headers.
 * (The CORS of the API is checked by the Api module.)
 */
final class ConfigurationCheck implements SecurityCheck
{
    private const GROUP = 'configuration';

    public function __construct(
        private readonly string $environment = ENVIRONMENT,
        private readonly ?App $app = null,
        private readonly ?Cookie $cookie = null,
        private readonly ?CspConfig $csp = null,
        private readonly ?EffectivePolicy $policy = null,
        private readonly ?bool $debug = null,
        private readonly ?bool $displayErrors = null,
    ) {
    }

    public function groups(): array
    {
        return [self::GROUP];
    }

    public function run(SecurityReport $report): void
    {
        $app    = $this->app ?? config(App::class);
        $cookie = $this->cookie ?? config(Cookie::class);
        $csp    = $this->csp ?? config(CspConfig::class);
        $policy = $this->policy ?? new EffectivePolicy($csp, null, null, null, $this->environment);
        // PHP's development server answering this computer is never forced to HTTPS (D-19-05):
        // say so instead of reporting what a real web server would not do.
        $local = $this->app === null && $this->environment === 'production' && App::isLocalDevServer();

        // Environment
        if ($this->environment === 'production') {
            $report->ok(self::GROUP, 'configuration.environment', lang('SecurityCheck.environment.title'), lang('SecurityCheck.environment.ok'));
        } else {
            $report->error(self::GROUP, 'configuration.environment', lang('SecurityCheck.environment.title'), lang('SecurityCheck.environment.error', [$this->environment]), lang('SecurityCheck.environment.fix'));
        }

        // The back-office prefix (D-40-01): information only.
        $report->info(self::GROUP, 'configuration.backendPath', lang('SecurityCheck.backendPath.title'), lang('SecurityCheck.backendPath.info', ['/' . BackendPath::current()]));

        // Debug toolbar and detailed errors
        $debug   = $this->debug ?? (defined('CI_DEBUG') && CI_DEBUG);
        $display = $this->displayErrors ?? (! is_cli() && in_array(strtolower((string) ini_get('display_errors')), ['1', 'on', 'true', 'stdout', 'stderr'], true));

        if (! $debug && ! $display) {
            $report->ok(self::GROUP, 'configuration.debug', lang('SecurityCheck.debug.title'), lang('SecurityCheck.debug.ok'));
        } else {
            $report->error(self::GROUP, 'configuration.debug', lang('SecurityCheck.debug.title'), lang('SecurityCheck.debug.error', [$debug ? 'CI_DEBUG' : 'display_errors']), lang('SecurityCheck.debug.fix'));
        }

        // HTTPS for every request
        if ($local) {
            $report->info(self::GROUP, 'configuration.https', lang('SecurityCheck.https.title'), lang('SecurityCheck.https.localServer'));
        } elseif ($app->forceGlobalSecureRequests) {
            $report->ok(self::GROUP, 'configuration.https', lang('SecurityCheck.https.title'), lang('SecurityCheck.https.ok'));
        } else {
            $report->error(self::GROUP, 'configuration.https', lang('SecurityCheck.https.title'), lang('SecurityCheck.https.error'), lang('SecurityCheck.https.fix'));
        }

        // Cookies
        $problems = [];

        if (! $cookie->secure && ! $local) {
            $problems[] = 'Secure';
        }

        if (! $cookie->httponly) {
            $problems[] = 'HttpOnly';
        }

        if (! in_array(strtolower($cookie->samesite), ['lax', 'strict'], true)) {
            $problems[] = 'SameSite=' . ($cookie->samesite === '' ? '—' : $cookie->samesite);
        }

        if ($problems === [] && $local && ! $cookie->secure) {
            $report->info(self::GROUP, 'configuration.cookies', lang('SecurityCheck.cookies.title'), lang('SecurityCheck.https.localServer'));
        } elseif ($problems === []) {
            $report->ok(self::GROUP, 'configuration.cookies', lang('SecurityCheck.cookies.title'), lang('SecurityCheck.cookies.ok', [$cookie->samesite]));
        } else {
            $report->error(self::GROUP, 'configuration.cookies', lang('SecurityCheck.cookies.title'), lang('SecurityCheck.cookies.error', [implode(', ', $problems)]), lang('SecurityCheck.cookies.fix'));
        }

        // Base URL and allowed host names
        $scheme = strtolower((string) parse_url($app->baseURL, PHP_URL_SCHEME));
        $host   = strtolower((string) parse_url($app->baseURL, PHP_URL_HOST));
        $hosts  = implode(', ', [$host, ...$app->allowedHostnames]);

        if ($scheme !== 'https') {
            $report->error(self::GROUP, 'configuration.hostnames', lang('SecurityCheck.hostnames.title'), lang('SecurityCheck.hostnames.http', [$app->baseURL]), lang('SecurityCheck.hostnames.fix'));
        } elseif ($host === '' || $host === 'localhost' || str_ends_with($host, '.localhost') || str_starts_with($host, '127.') || $host === '[::1]' || $host === 'example.com') {
            $report->warning(self::GROUP, 'configuration.hostnames', lang('SecurityCheck.hostnames.title'), lang('SecurityCheck.hostnames.local', [$app->baseURL]), lang('SecurityCheck.hostnames.fix'));
        } else {
            $report->ok(self::GROUP, 'configuration.hostnames', lang('SecurityCheck.hostnames.title'), lang('SecurityCheck.hostnames.ok', [$hosts]));
        }

        // Content-Security-Policy
        $header = $policy->csp($app->CSPEnabled);
        $unsafe = EffectivePolicy::unsafeSources($header['value']);

        if (! $app->CSPEnabled || $header['value'] === '') {
            $report->error(self::GROUP, 'configuration.csp', lang('SecurityCheck.csp.title'), lang('SecurityCheck.csp.disabled'), lang('SecurityCheck.csp.fix'));
        } elseif ($unsafe !== []) {
            $report->error(self::GROUP, 'configuration.csp', lang('SecurityCheck.csp.title'), lang('SecurityCheck.csp.unsafe', [implode(', ', $unsafe)]), lang('SecurityCheck.csp.fixUnsafe'));
        } elseif ($csp->reportOnly) {
            $report->error(self::GROUP, 'configuration.csp', lang('SecurityCheck.csp.title'), lang('SecurityCheck.csp.reportOnly'), lang('SecurityCheck.csp.fixReportOnly'));
        } else {
            $report->ok(self::GROUP, 'configuration.csp', lang('SecurityCheck.csp.title'), lang('SecurityCheck.csp.ok'));
        }

        // HSTS and the other headers
        $headers = $policy->headers();
        $hsts    = $headers['Strict-Transport-Security'] ?? '';

        if ($hsts === '') {
            $report->warning(self::GROUP, 'configuration.hsts', lang('SecurityCheck.hsts.title'), lang('SecurityCheck.hsts.missing'), lang('SecurityCheck.hsts.fix'));
        } else {
            $report->ok(self::GROUP, 'configuration.hsts', lang('SecurityCheck.hsts.title'), $hsts);
        }

        $missing = array_values(array_filter(['X-Content-Type-Options', 'X-Frame-Options', 'Referrer-Policy', 'Permissions-Policy'], static fn (string $name): bool => ($headers[$name] ?? '') === ''));

        if ($missing === []) {
            $report->ok(self::GROUP, 'configuration.headers', lang('SecurityCheck.headers.title'), lang('SecurityCheck.headers.ok'));
        } else {
            $report->warning(self::GROUP, 'configuration.headers', lang('SecurityCheck.headers.title'), lang('SecurityCheck.headers.missing', [implode(', ', $missing)]), lang('SecurityCheck.headers.fix'));
        }
    }
}
