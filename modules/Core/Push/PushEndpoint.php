<?php

declare(strict_types=1);

namespace Modules\Core\Push;

use Config\Push;

/**
 * Validates the endpoint of a browser subscription before it is stored (SSRF protection,
 * D-44-02): `https://` on port 443, no user information, a host of `Config\Push::$serviceHosts`
 * (exact name or `*.example.com` for its subdomains), never an IP address or a local name. The
 * address the host resolves to is checked again by OutboundClient on every delivery.
 */
final class PushEndpoint
{
    public const MAX_LENGTH = 1000;

    /**
     * @param list<string>|null $hosts default: Config\Push::$serviceHosts
     *
     * @return string|null why it is refused (url, scheme, host), or null when accepted
     */
    public static function problem(string $endpoint, ?array $hosts = null): ?string
    {
        if ($endpoint === '' || strlen($endpoint) > self::MAX_LENGTH || preg_match('/[\x00-\x20\x7F\\\\]/', $endpoint) === 1) {
            return 'url';
        }

        $parts = parse_url($endpoint);

        if (! is_array($parts) || ! isset($parts['scheme'], $parts['host']) || isset($parts['user']) || isset($parts['pass'])) {
            return 'url';
        }

        if (strtolower($parts['scheme']) !== 'https' || (isset($parts['port']) && (int) $parts['port'] !== 443)) {
            return 'scheme';
        }

        $host = rtrim(strtolower(trim($parts['host'], '[]')), '.');

        if (filter_var($host, FILTER_VALIDATE_IP) !== false || ! str_contains($host, '.')) {
            return 'host';
        }

        return self::allowedHost($host, $hosts ?? config(Push::class)->serviceHosts) ? null : 'host';
    }

    /**
     * @param list<string> $hosts
     */
    public static function allowedHost(string $host, array $hosts): bool
    {
        foreach ($hosts as $entry) {
            $entry = rtrim(strtolower(trim($entry)), '.');

            if ($entry === $host) {
                return true;
            }

            if (str_starts_with($entry, '*.') && strlen($entry) > 2 && str_ends_with($host, substr($entry, 1)) && strlen($host) > strlen($entry) - 1) {
                return true;
            }
        }

        return false;
    }
}
