<?php

declare(strict_types=1);

namespace Modules\Core\Security\Check;

use CodeIgniter\HTTP\ContentSecurityPolicy;
use CodeIgniter\HTTP\Response;
use Config\App;
use Config\ContentSecurityPolicy as CspConfig;
use Config\SecurityHeaders as SecurityHeadersConfig;
use Modules\Core\Filters\ContentSecurity;
use Modules\Core\Filters\SecurityHeaders;
use Modules\Core\Registry\DeclarationRegistry;
use Throwable;

/**
 * The Content-Security-Policy and security headers a page of this installation gets: the
 * configuration (`Config\ContentSecurityPolicy`, `Config\SecurityHeaders`), the sources enabled
 * modules declare in `$csp` and the origins of Settings › Security, exactly as the
 * `cspmodules` and `securityheaders` filters apply them. Nonces are shown as `'nonce-…'`.
 */
final class EffectivePolicy
{
    /** CSP keywords that weaken the policy (never allowed). */
    public const UNSAFE = ["'unsafe-inline'", "'unsafe-eval'", "'unsafe-hashes'", "'wasm-unsafe-eval'"];

    /**
     * @param array<string, list<string>>|null $moduleSources  Default: the enabled modules' `$csp`
     * @param array<string, list<string>>|null $settingSources Default: Settings › Security
     */
    public function __construct(
        private readonly ?CspConfig $config = null,
        private readonly ?SecurityHeadersConfig $headers = null,
        private readonly ?array $moduleSources = null,
        private readonly ?array $settingSources = null,
        private readonly string $environment = ENVIRONMENT,
    ) {
    }

    /**
     * The header name (`Content-Security-Policy` or `…-Report-Only`) and its value, empty when
     * CSP is disabled (`Config\App::$CSPEnabled`).
     *
     * @return array{name: string, value: string}
     */
    public function csp(?bool $enabled = null): array
    {
        $config = $this->config ?? config(CspConfig::class);

        if (! ($enabled ?? config(App::class)->CSPEnabled)) {
            return ['name' => 'Content-Security-Policy', 'value' => ''];
        }

        $csp = new ContentSecurityPolicy(clone $config);
        ContentSecurity::apply($csp, $this->moduleSources ?? self::readModuleSources());
        ContentSecurity::apply($csp, $this->settingSources ?? ContentSecurity::settingSources());

        $response = new Response(config(App::class));
        $response->setBody('');
        $this->finalize($csp, $response);

        $name  = $config->reportOnly ? 'Content-Security-Policy-Report-Only' : 'Content-Security-Policy';
        $value = $response->getHeaderLine($name);

        return ['name' => $name, 'value' => (string) preg_replace("/'nonce-[^']+'/", "'nonce-…'", $value)];
    }

    /**
     * Directive => sources of a policy header value.
     *
     * @return array<string, list<string>>
     */
    public static function directives(string $policy): array
    {
        $directives = [];

        foreach (explode(';', $policy) as $part) {
            $tokens = preg_split('/\s+/', trim($part)) ?: [];
            $name   = strtolower((string) array_shift($tokens));

            if ($name !== '') {
                $directives[$name] = array_values(array_filter($tokens, static fn (string $token): bool => $token !== ''));
            }
        }

        return $directives;
    }

    /**
     * Unsafe sources of a policy: `directive source` strings (empty when none).
     *
     * @return list<string>
     */
    public static function unsafeSources(string $policy): array
    {
        $found = [];

        foreach (self::directives($policy) as $directive => $sources) {
            foreach ($sources as $source) {
                $lower = strtolower($source);

                if (in_array($lower, self::UNSAFE, true) || (in_array($directive, ['script-src', 'script-src-elem', 'style-src', 'style-src-elem', 'default-src', 'object-src'], true) && in_array($lower, ['*', 'http:', 'https:', 'data:'], true))) {
                    $found[] = $directive . ' ' . $source;
                }
            }
        }

        return $found;
    }

    /**
     * The other security headers (Config\SecurityHeaders, as the `securityheaders` filter sends
     * them in this environment).
     *
     * @return array<string, string>
     */
    public function headers(): array
    {
        return SecurityHeaders::headers($this->headers ?? config(SecurityHeadersConfig::class), $this->environment);
    }

    /**
     * @return array<string, list<string>>
     */
    private static function readModuleSources(): array
    {
        try {
            return (new DeclarationRegistry())->cspSources();
        } catch (Throwable) {
            return [];
        }
    }

    private function finalize(ContentSecurityPolicy $csp, Response $response): void
    {
        $csp->finalize($response);

        if ($response->getHeaderLine('Content-Security-Policy') === '' && $response->getHeaderLine('Content-Security-Policy-Report-Only') === '') {
            // Older finalize() only builds the header when the response has a nonce placeholder.
            $csp->getScriptNonce();
            $csp->finalize($response);
        }
    }
}
