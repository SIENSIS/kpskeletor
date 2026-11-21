<?php

declare(strict_types=1);

namespace Modules\Pages\Services;

/**
 * The page the current request shows, for the visibility rules of the theme zones (D-47-01):
 * set by `PageRenderer` (a page, or the home page). On other public screens (news, events…)
 * nothing is set: the page is "another page", and the home page is the site's root path.
 */
final class CurrentPage
{
    private static ?int $id = null;

    private static ?bool $home = null;

    public static function set(int $id, bool $home): void
    {
        self::$id   = $id;
        self::$home = $home;
    }

    public static function reset(): void
    {
        self::$id = self::$home = null;
    }

    public static function id(): ?int
    {
        return self::$id;
    }

    public static function isHome(): bool
    {
        if (self::$home !== null) {
            return self::$home;
        }

        // The theme's own home (no home page chosen): the root path, with or without a language prefix.
        $path = trim(uri_string(), '/');

        return $path === '' || in_array($path, \Modules\Core\I18n\Locales::available(), true);
    }
}
