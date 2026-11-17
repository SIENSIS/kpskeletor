<?php

declare(strict_types=1);

namespace Modules\Core\I18n;

use Modules\Core\Settings\SecurityRules;

/**
 * Validation rule `supported_locales` (registered by the Core's registrar): a list of locales
 * (one per line or separated by commas) that are all in `Config\App::$supportedLocales`; used
 * by the setting `Core.locales`.
 */
final class LocaleRules
{
    public function supported_locales(mixed $str = null, ?string &$error = null): bool
    {
        foreach (SecurityRules::lines($str) as $locale) {
            if (! in_array($locale, Locales::supported(), true)) {
                $message = lang('Core.validation.supportedLocales', [$locale, implode(', ', Locales::supported())]);
                $error   = is_string($message) ? $message : 'Core.validation.supportedLocales';

                return false;
            }
        }

        return true;
    }
}
