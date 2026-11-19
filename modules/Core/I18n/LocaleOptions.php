<?php

declare(strict_types=1);

namespace Modules\Core\I18n;

use Modules\Core\Settings\SettingOptionsProvider;

/**
 * Choices of the setting `Core.locales` (docs/i18n.md): every supported locale
 * (`Config\App::$supportedLocales`) by its own name, so a language added by configuration can be
 * offered in Settings › General without changing the Core's manifest (session 37).
 */
final class LocaleOptions implements SettingOptionsProvider
{
    public function options(): array
    {
        $options = [];

        foreach (Locales::supported() as $locale) {
            $options[$locale] = Locales::name($locale);
        }

        return $options;
    }
}
