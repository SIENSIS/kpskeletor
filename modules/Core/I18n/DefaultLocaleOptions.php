<?php

declare(strict_types=1);

namespace Modules\Core\I18n;

use Config\App;
use Modules\Core\Settings\SettingOptionsProvider;

/**
 * Choices of the setting `Core.defaultLocale` (docs/i18n.md): "as configured"
 * (`Config\App::$defaultLocale`) or any supported locale by its own name (session 37).
 */
final class DefaultLocaleOptions implements SettingOptionsProvider
{
    public function options(): array
    {
        return ['' => lang('Core.settings.defaultLocaleConfig', [Locales::name((string) config(App::class)->defaultLocale)])]
            + (new LocaleOptions())->options();
    }
}
