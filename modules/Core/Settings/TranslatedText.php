<?php

declare(strict_types=1);

namespace Modules\Core\Settings;

use Modules\Core\I18n\Locales;

/**
 * Settings of type `translated` (D-25-10, docs/settings.md): one setting stored as JSON
 * (locale => text). The manifest's `default` is a language key (its text in every supported
 * locale) or a map; the Settings form shows one input per available locale of the site.
 *
 * The text for a locale: the stored text of that locale, else the Catalan one (a locale added
 * later has no text yet), else the default of that locale, else the Catalan default.
 */
final class TranslatedText
{
    public const FALLBACK_LOCALE = 'ca';

    /**
     * The text of a `translated` setting in a locale (default: the current one).
     */
    public static function get(string $setting, ?string $locale = null): string
    {
        $definition = (new SettingsRegistry())->find($setting);
        $defaults   = is_array($definition?->default) ? $definition->default : [];

        try {
            $stored = setting($setting);
        } catch (\Throwable) {
            $stored = null; // not installed yet
        }

        return self::resolve(SettingCaster::cast('translated', $stored ?? []), $defaults, $locale ?? service('language')->getLocale());
    }

    /**
     * @param array<string, string> $texts    the stored map
     * @param array<string, string> $defaults the default map
     */
    public static function resolve(array $texts, array $defaults, string $locale): string
    {
        $base = explode('-', $locale, 2)[0];

        foreach ([[$texts, $locale], [$texts, $base], [$texts, self::FALLBACK_LOCALE], [$defaults, $locale], [$defaults, $base], [$defaults, self::FALLBACK_LOCALE]] as [$map, $key]) {
            if (isset($map[$key]) && trim($map[$key]) !== '') {
                return $map[$key];
            }
        }

        return '';
    }

    /**
     * The text of a language key in every supported locale (only the locales whose language
     * files define it, so the Catalan fallback applies to the others).
     *
     * @return array<string, string>
     */
    public static function defaults(string $languageKey): array
    {
        $language = service('language');
        $texts    = [];

        foreach (Locales::supported() as $locale) {
            if (method_exists($language, 'has') && ! $language->has($languageKey, $locale)) {
                continue;
            }

            $text = lang($languageKey, [], $locale);

            if (is_string($text) && $text !== $languageKey) {
                $texts[$locale] = $text;
            }
        }

        ksort($texts);

        return $texts;
    }
}
