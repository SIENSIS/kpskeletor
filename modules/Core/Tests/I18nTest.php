<?php

declare(strict_types=1);

namespace Modules\Core\Tests;

use Config\App;
use Config\Services;
use Modules\Auth\Tests\AuthTestCase;
use Modules\Core\I18n\DefaultLocaleOptions;
use Modules\Core\I18n\Language;
use Modules\Core\I18n\LocaleNegotiator;
use Modules\Core\I18n\LocaleOptions;
use Modules\Core\I18n\Locales;
use Modules\Core\Notifications\RecipientLocale;

/**
 * Languages (docs/i18n.md): the fallback chain of the language service, the negotiation
 * (profile → browser → default) restricted to the available locales, the Catalan default.
 *
 * @internal
 */
final class I18nTest extends AuthTestCase
{
    private string $configuredDefault;

    protected function setUp(): void
    {
        parent::setUp();
        $this->configuredDefault = config(App::class)->defaultLocale;
    }

    protected function tearDown(): void
    {
        config(App::class)->defaultLocale = $this->configuredDefault;
        Services::resetSingle('settings'); // no restricted locales left for the next test classes
        Services::resetSingle('language');
        Services::resetSingle('request');

        parent::tearDown();
    }

    public function testTheSkeletonIsCatalanWithSpanishAsTheFallback(): void
    {
        $file = new App(); // the file's values, without the tests' .env override
        $this->assertSame(['ca', 'es', 'en'], $file->supportedLocales);
        $this->assertSame(['es', 'en'], $file->localeFallbacks);
        $this->assertFalse($file->negotiateLocale);

        $source = (string) file_get_contents(APPPATH . 'Config/App.php');
        $this->assertStringContainsString("public string \$defaultLocale = 'ca';", $source);
        $this->assertInstanceOf(Language::class, service('language'));
    }

    public function testTheFallbackChainIsRequestedBaseSpanishEnglish(): void
    {
        $language = new Language('ca-ES');
        $this->assertSame(['ca-ES', 'ca', 'es', 'en'], $language->chain());
        $this->assertSame(['fr', 'es', 'en'], $language->chain('fr'));

        // A line only in Spanish and English: Catalan falls back to Spanish, not to English.
        $this->assertSame('Fecha:', (new Language('eu'))->getLine('Auth.emailDate'), 'No Basque anywhere: Spanish, not English.');
        $this->assertSame('Data:', (new Language('ca'))->getLine('Auth.emailDate'), 'The Catalan copy of Shield\'s texts.');
        $this->assertSame('Català', (new Language('ca-ES'))->getLine('Core.locales.ca'), 'ca-ES uses ca.');
        $this->assertSame('El camp Nom és obligatori.', (new Language('ca'))->getLine('Validation.required', ['field' => 'Nom']));
        $this->assertSame('Missing.key', (new Language('ca'))->getLine('Missing.key'));
        $this->assertTrue((new Language('ca'))->has('Core.locales.ca'));
        $this->assertFalse((new Language('ca'))->has('Core.nothing.here'));
    }

    public function testASystemLineMissingInCatalanUsesSpanish(): void
    {
        $spanish = require ROOTPATH . 'vendor/codeigniter4/translations/Language/es/HTTP.php';
        $this->assertFileDoesNotExist(APPPATH . 'Language/ca/HTTP.php');
        $this->assertSame($spanish['methodNotFound'], (new Language('ca'))->getLine('HTTP.methodNotFound'));
    }

    public function testTheNegotiatorPrefersTheProfileThenTheBrowserThenTheDefault(): void
    {
        $negotiator = new LocaleNegotiator(['ca', 'es', 'en'], 'ca');

        $this->assertSame('en', $negotiator->negotiate('en', 'es-ES,es;q=0.9'));
        $this->assertSame('es', $negotiator->negotiate(null, 'es-ES,es;q=0.9,en;q=0.8'));
        $this->assertSame('en', $negotiator->negotiate(null, 'fr-FR, en-GB;q=0.8, es;q=0.5'));
        $this->assertSame('es', $negotiator->negotiate(null, 'en;q=0.4, es;q=0.6'));
        $this->assertSame('ca', $negotiator->negotiate(null, 'fr, de'));
        $this->assertSame('ca', $negotiator->negotiate(null, ''));
        $this->assertSame('ca', $negotiator->negotiate(null, '*'));
        $this->assertSame('ca', $negotiator->negotiate(null, 'en;q=0'));
        $this->assertSame('ca', $negotiator->negotiate('fr', '<script>alert(1)</script>, ../en'), 'Invalid input is ignored.');

        $restricted = new LocaleNegotiator(['ca', 'es'], 'ca');
        $this->assertSame('ca', $restricted->negotiate('en', 'en'), 'A locale the site does not offer is never chosen.');
    }

    public function testTheAvailableLocalesAndTheDefaultComeFromTheSettings(): void
    {
        config(App::class)->defaultLocale = 'ca';
        $this->assertSame(['ca', 'es', 'en'], Locales::available());
        $this->assertSame('ca', Locales::default());

        setting('Core.locales', ['en', 'es', 'xx']);
        $this->assertSame(['es', 'en'], Locales::available(), 'Supported order; unknown codes dropped.');
        $this->assertSame('es', Locales::default(), 'The configured default is not available: the first available.');

        setting('Core.defaultLocale', 'en');
        $this->assertSame('en', Locales::default());
        $this->assertSame(['es' => 'Español', 'en' => 'English'], Locales::names());
    }

    public function testTheLanguageSettingsOfferEverySupportedLocale(): void
    {
        config(App::class)->defaultLocale = 'ca';
        $this->assertSame(['ca' => 'Català', 'es' => 'Español', 'en' => 'English'], (new LocaleOptions())->options());
        $this->assertSame(['' => lang('Core.settings.defaultLocaleConfig', ['Català']), 'ca' => 'Català', 'es' => 'Español', 'en' => 'English'], (new DefaultLocaleOptions())->options());

        // A language added by configuration can be chosen without changing the Core (session 37).
        config(App::class)->supportedLocales = ['ca', 'es', 'en', 'fr'];
        $this->assertArrayHasKey('fr', (new LocaleOptions())->options());
        $this->assertArrayHasKey('fr', (new DefaultLocaleOptions())->options());
    }

    public function testAGuestGetsTheBrowserLanguageOrTheCatalanDefault(): void
    {
        config(App::class)->defaultLocale = 'ca';

        $page = $this->withHeaders(['Accept-Language' => 'es-ES,es;q=0.9'])->send('GET', 'login');
        $this->assertStringContainsString('<html lang="es"', (string) $page->response()->getBody());

        $page = $this->withHeaders(['Accept-Language' => 'de-DE'])->send('GET', 'login');
        $this->assertStringContainsString('<html lang="ca"', (string) $page->response()->getBody());

        setting('Core.locales', ['ca', 'en']);
        $page = $this->withHeaders(['Accept-Language' => 'es'])->send('GET', 'login');
        $this->assertStringContainsString('<html lang="ca"', (string) $page->response()->getBody(), 'Spanish is not offered.');
    }

    public function testTheProfileLanguageWinsOnlyWhenTheSiteOffersIt(): void
    {
        $user = $this->createUser('ana@example.com', 'admin');
        db_connect()->table('user_profiles')->where('user_id', $user->id)->delete();
        db_connect()->table('user_profiles')->insert(['user_id' => $user->id, 'name' => 'Ana', 'locale' => 'en']);
        $this->login('ana@example.com');

        $page = $this->withHeaders(['Accept-Language' => 'es'])->send('GET', 'backend/profile');
        $this->assertStringContainsString('<html lang="en"', (string) $page->response()->getBody());

        setting('Core.locales', ['ca', 'es']);
        $page = $this->withHeaders(['Accept-Language' => 'es'])->send('GET', 'backend/profile');
        $body = (string) $page->response()->getBody();
        $this->assertStringContainsString('<html lang="es"', $body, 'English is no longer offered: the browser\'s language.');
        $this->assertStringNotContainsString('value="en"', $body, 'The profile only offers the available locales.');
    }

    public function testTheProfileRefusesALocaleTheSiteDoesNotOffer(): void
    {
        setting('Core.locales', ['ca', 'es']);
        $this->assertSame(['ca', 'es'], Locales::available());
        $this->assertFalse(Locales::isAvailable('en'));
        $this->assertSame('ca', (new LocaleNegotiator(null, 'ca'))->negotiate('en', ''));
    }

    public function testEmailsOfUsersWithoutALanguageUseTheSiteDefault(): void
    {
        $user = $this->createUser('pau@example.com');
        db_connect()->table('user_profiles')->where('user_id', $user->id)->delete();

        setting('Core.defaultLocale', 'es');
        $this->assertSame('es', RecipientLocale::for($user));

        db_connect()->table('user_profiles')->insert(['user_id' => $user->id, 'name' => 'Pau', 'locale' => 'en']);
        $this->assertSame('en', RecipientLocale::for($user));

        setting('Core.locales', ['ca', 'es']);
        $this->assertSame('es', RecipientLocale::for($user), 'English is not offered: the site default.');
    }

    public function testTheApiNegotiatesAmongTheAvailableLocales(): void
    {
        config(App::class)->defaultLocale = 'ca';
        setting('Core.locales', ['ca', 'en']);

        $response = $this->withHeaders(['Accept-Language' => 'es'])->call('GET', 'api/v1/me');
        $this->assertSame(401, $response->response()->getStatusCode());
        $this->assertSame('ca', service('request')->getLocale(), 'Spanish is not offered: the default.');

        $this->withHeaders(['Accept-Language' => 'en-GB'])->call('GET', 'api/v1/me');
        $this->assertSame('en', service('request')->getLocale());
    }
}
