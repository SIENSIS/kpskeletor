<?php

declare(strict_types=1);

namespace Modules\Core\I18n;

use Config\App;
use Throwable;

/**
 * The site's locales: the supported ones (`Config\App::$supportedLocales`), the available ones
 * (setting `Core.locales`, empty = every supported locale) and the default one (setting
 * `Core.defaultLocale`, empty = `Config\App::$defaultLocale`; always one of the available ones).
 *
 * Settings are read only when the database is reachable: before installation (or when the
 * settings table cannot be read) the configuration applies.
 */
final class Locales
{
    /**
     * @return list<string>
     */
    public static function supported(): array
    {
        $supported = array_values(array_filter(config(App::class)->supportedLocales, 'is_string'));

        return $supported === [] ? ['ca'] : $supported;
    }

    /**
     * The locales the site offers, in the order of the supported ones.
     *
     * @return list<string>
     */
    public static function available(): array
    {
        $supported = self::supported();
        $chosen    = self::setting('Core.locales');
        $chosen    = is_array($chosen) ? array_filter($chosen, 'is_string') : [];
        $available = array_values(array_intersect($supported, $chosen));

        return $available === [] ? $supported : $available;
    }

    public static function default(): string
    {
        $available = self::available();

        foreach ([self::setting('Core.defaultLocale'), config(App::class)->defaultLocale] as $candidate) {
            if (is_string($candidate) && in_array($candidate, $available, true)) {
                return $candidate;
            }
        }

        return $available[0];
    }

    public static function isAvailable(?string $locale): bool
    {
        return is_string($locale) && in_array($locale, self::available(), true);
    }

    /**
     * The name of a locale in its own language (`Català`, `Español`, `English`).
     */
    public static function name(string $locale): string
    {
        $name = lang('Core.locales.' . $locale, [], $locale);

        return $name === 'Core.locales.' . $locale ? $locale : $name;
    }

    /**
     * Available locale => its name, for selectors.
     *
     * @return array<string, string>
     */
    public static function names(): array
    {
        $names = [];

        foreach (self::available() as $locale) {
            $names[$locale] = self::name($locale);
        }

        return $names;
    }

    private static function setting(string $name): mixed
    {
        if (! service('installstate')->isConfigured()) {
            return null;
        }

        try {
            return setting($name);
        } catch (Throwable) {
            return null;
        }
    }
}
