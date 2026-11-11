<?php

declare(strict_types=1);

namespace Modules\Menus\Services;

/**
 * Addresses of menu items and custom links (docs/specification.md, "Modules" › "Menus"): only
 * `https://` and `http://` URLs with a host (no user name or password) and internal paths
 * starting with a single `/`. Everything else is refused: `javascript:`, `data:`, `vbscript:`,
 * `mailto:`, protocol-relative `//host`, backslashes, spaces and control characters (which
 * browsers strip, so `java\tscript:` would still run).
 */
final class UrlValidator
{
    public const MAX_LENGTH = 500;

    /**
     * The address when it is acceptable, else null.
     */
    public static function check(string $url): ?string
    {
        $url = trim($url);

        if ($url === '' || strlen($url) > self::MAX_LENGTH || preg_match('/[\s\x00-\x1F\x7F\\\\]/u', $url) === 1 || preg_match('//u', $url) !== 1) {
            return null;
        }

        if (self::isInternal($url)) {
            return $url;
        }

        if (preg_match('#^https?://#i', $url) !== 1) {
            return null;
        }

        $parts = parse_url($url);

        if (! is_array($parts) || ($parts['host'] ?? '') === '' || isset($parts['user']) || isset($parts['pass'])) {
            return null;
        }

        return $url;
    }

    /** An internal path: `/` followed by anything but a second `/` (protocol-relative). */
    public static function isInternal(string $url): bool
    {
        return str_starts_with($url, '/') && ! str_starts_with($url, '//');
    }

    /** The path part of an internal address, without the leading slash, query or fragment. */
    public static function path(string $url): string
    {
        $path = (string) parse_url('http://x' . $url, PHP_URL_PATH);

        return trim(rawurldecode($path), '/');
    }
}
