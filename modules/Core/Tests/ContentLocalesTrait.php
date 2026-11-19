<?php

declare(strict_types=1);

namespace Modules\Core\Tests;

use CodeIgniter\Config\Factories;
use Config\App;
use Config\Kpapy;
use Modules\Core\Content\ContentTranslations;

/**
 * For module tests of translated content (docs/i18n.md, "Content languages"): configures a site
 * with a fourth language and another default language, as an administrator would by
 * configuration (`Config\App::$supportedLocales`, `Core.locales`, `Core.defaultLocale`), and
 * checks the translation tabs of a kpcrudy form.
 *
 * Use it in a test case whose site is installed (settings table available); call
 * `useContentLocales()` or `useShippedContentLocales()` before the requests.
 */
trait ContentLocalesTrait
{
    /**
     * The shipped languages (`ca`, `es`, `en`, Catalan the site's default and so the required
     * content language) visited by a browser asking for English: the tests run with
     * `app.defaultLocale=en` to assert English texts, which would otherwise make English the
     * default content language.
     */
    protected function useShippedContentLocales(): void
    {
        setting('Core.defaultLocale', 'ca');
        $this->withHeaders(['Accept-Language' => 'en']);
    }

    /**
     * Site languages `ca`, `es`, `en` and `$extra`, with `$default` as the default one.
     */
    protected function useContentLocales(string $extra = 'fr', string $default = 'es'): void
    {
        $locales = ['ca', 'es', 'en', $extra];

        config(App::class)->supportedLocales = $locales;
        setting('Core.locales', $locales);
        setting('Core.defaultLocale', $default);

        // kpapy's configuration reads the default language when it is built.
        Factories::injectMock('config', 'Kpapy', new Kpapy());

        $this->assertSame($locales, ContentTranslations::codes());
        $this->assertSame($default, ContentTranslations::defaultLocale());
    }

    /**
     * The form has one input of `$field` per language and only the default language's is required.
     */
    protected function assertTranslationTabs(string $html, string $field, ?string $required = null): void
    {
        $required ??= ContentTranslations::defaultLocale();

        foreach (ContentTranslations::codes() as $locale) {
            $name = preg_quote('kp_tr[' . $locale . '][' . $field . ']', '/');
            $this->assertMatchesRegularExpression('/<(input|textarea)[^>]*name="' . $name . '"/', $html, "The {$locale} tab has {$field}.");
            $tag = preg_match('/<(?:input|textarea)[^>]*name="' . $name . '"[^>]*>/', $html, $m) === 1 ? $m[0] : '';

            if ($locale === $required) {
                $this->assertMatchesRegularExpression('/\srequired[\s>=]/', $tag, "{$locale} is required.");
            } else {
                $this->assertDoesNotMatchRegularExpression('/\srequired[\s>=]/', $tag, "{$locale} is optional.");
            }
        }
    }
}
