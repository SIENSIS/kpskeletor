<?php

declare(strict_types=1);

namespace Modules\Core\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Config\SecurityHeaders as SecurityHeadersConfig;

/**
 * Required "after" filter (`securityheaders`): the security headers of `Config\SecurityHeaders`
 * on every response, also for routes that do not exist (docs/specification.md, "Security
 * baseline"; decision D-00-28). Replaces CodeIgniter's `secureheaders` filter.
 *
 * Strict-Transport-Security is sent in production (or when `hsts` is true), with a max-age of
 * at least one year. Headers a controller already set are kept.
 */
final class SecurityHeaders implements FilterInterface
{
    public const MIN_HSTS_MAX_AGE = 31536000;

    /**
     * @param list<string>|null $arguments
     */
    public function before(RequestInterface $request, $arguments = null)
    {
        return null;
    }

    /**
     * @param list<string>|null $arguments
     */
    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null): ResponseInterface
    {
        foreach (self::headers(config(SecurityHeadersConfig::class)) as $name => $value) {
            if (! $response->hasHeader($name)) {
                $response->setHeader($name, $value);
            }
        }

        return $response;
    }

    /**
     * The headers the configuration produces (empty values are left out).
     *
     * @return array<string, string>
     */
    public static function headers(SecurityHeadersConfig $config, string $environment = ENVIRONMENT): array
    {
        $headers = [];

        if ($config->hsts ?? ($environment === 'production')) {
            $headers['Strict-Transport-Security'] = 'max-age=' . max(self::MIN_HSTS_MAX_AGE, $config->hstsMaxAge)
                . ($config->hstsIncludeSubDomains ? '; includeSubDomains' : '')
                . ($config->hstsPreload ? '; preload' : '');
        }

        $headers['Referrer-Policy']              = $config->referrerPolicy;
        $headers['X-Content-Type-Options']       = $config->contentTypeOptions;
        $headers['X-Frame-Options']              = $config->frameOptions;
        $headers['Cross-Origin-Opener-Policy']   = $config->crossOriginOpenerPolicy;

        $features = [];

        foreach ($config->permissionsPolicy as $feature => $allow) {
            $features[] = $feature . '=' . $allow;
        }

        $headers['Permissions-Policy'] = implode(', ', $features);

        foreach ($config->extra as $name => $value) {
            $headers[$name] = $value;
        }

        return array_filter($headers, static fn (string $value): bool => trim($value) !== '');
    }
}
