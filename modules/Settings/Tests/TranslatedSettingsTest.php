<?php

declare(strict_types=1);

namespace Modules\Settings\Tests;

use Modules\Core\I18n\Locales;
use Modules\Core\Settings\TranslatedText;

/**
 * The "translated text" setting type (D-25-10): one input per available locale, stored as one
 * map, texts of locales the site no longer offers kept; and the locale settings of the General tab.
 *
 * @internal
 */
final class TranslatedSettingsTest extends SettingsTestCase
{
    /** @var array<string, string> */
    private const FORM = [
        'Users-birthdaysCalendar'           => '0',
        'Users-birthdayGreetingSharedOnly'  => '1',
        'Users-birthdayGreetingTime'        => '08:00',
        'Users-impersonationMinutes'        => '30',
    ];

    protected function tearDown(): void
    {
        \Config\Services::resetSingle('settings'); // no restricted locales left for the next test classes
        \Config\Services::resetSingle('language');
        parent::tearDown();
    }

    public function testTheFormShowsOneInputPerAvailableLocaleWithTheDefaults(): void
    {
        $this->loginWith('settings.update', 'settings.users');

        $page = $this->send('GET', 'backend/settings/users');
        $this->assertStringContainsString('name="Users-birthdayGreeting[ca]"', (string) $page->response()->getBody());
        $this->assertStringContainsString('name="Users-birthdayGreeting[es]"', (string) $page->response()->getBody());
        $this->assertStringContainsString('name="Users-birthdayGreeting[en]"', (string) $page->response()->getBody());
        $this->assertStringContainsString(esc('¡Feliz cumpleaños, {name}! 🎉', 'attr'), (string) $page->response()->getBody());
        $page->assertSee('Català');

        setting('Core.locales', ['ca', 'es']);
        $page = $this->send('GET', 'backend/settings/users');
        $this->assertStringContainsString('name="Users-birthdayGreeting[es]"', (string) $page->response()->getBody());
        $this->assertStringNotContainsString('name="Users-birthdayGreeting[en]"', (string) $page->response()->getBody());
    }

    public function testTheTextsAreSavedAsOneMapAndOtherLocalesAreKept(): void
    {
        $this->loginWith('settings.update', 'settings.users');
        setting('Users.birthdayGreeting', ['ca' => 'Antic', 'en' => 'Old English, {name}']);
        setting('Core.locales', ['ca', 'es']);

        $this->send('POST', 'backend/settings/users', self::FORM + [
            'Users-birthdayGreeting' => ['ca' => ' Molts anys, {name}! ', 'es' => 'Felicidades, {name}', 'en' => 'Forged', 'xx' => 'Forged'],
        ])->assertRedirectTo(site_url('backend/settings/users'));

        $this->assertSame(['ca' => 'Molts anys, {name}!', 'en' => 'Old English, {name}', 'es' => 'Felicidades, {name}'], setting('Users.birthdayGreeting'));
        $this->assertSame('Felicidades, {name}', TranslatedText::get('Users.birthdayGreeting', 'es'));
    }

    public function testEveryAvailableLocaleIsValidatedAndAStringIsRefused(): void
    {
        $this->loginWith('settings.update', 'settings.users');

        $page = $this->send('POST', 'backend/settings/users', self::FORM + [
            'Users-birthdayGreeting' => ['ca' => 'Bé', 'es' => '', 'en' => str_repeat('a', 301)],
        ]);
        $page->assertStatus(422);
        $this->assertSame(0, $this->storedCount('Users', 'birthdayGreeting'));

        $this->send('POST', 'backend/settings/users', self::FORM + ['Users-birthdayGreeting' => 'not a map'])->assertStatus(422);
        $this->assertSame(0, db_connect()->table('settings')->where('key', 'birthdayGreeting')->countAllResults(), 'A string instead of a map stores nothing.');
    }

    public function testSavingTheDefaultsStoresNothing(): void
    {
        $this->loginWith('settings.update', 'settings.users');

        $this->send('POST', 'backend/settings/users', self::FORM + [
            'Users-birthdayGreeting' => ['ca' => 'Per molts anys, {name}! 🎉', 'es' => '¡Feliz cumpleaños, {name}! 🎉', 'en' => 'Happy birthday, {name}! 🎉'],
        ])->assertRedirectTo(site_url('backend/settings/users'));

        $this->assertSame(0, $this->storedCount('Users', 'birthdayGreeting'));
    }

    public function testTheResolutionFallsBackToCatalanThenToTheDefault(): void
    {
        $defaults = ['ca' => 'Default ca', 'es' => 'Default es'];

        $this->assertSame('Text es', TranslatedText::resolve(['ca' => 'Text ca', 'es' => 'Text es'], $defaults, 'es'));
        $this->assertSame('Text es', TranslatedText::resolve(['ca' => 'Text ca', 'es' => 'Text es'], $defaults, 'es-ES'));
        $this->assertSame('Text ca', TranslatedText::resolve(['ca' => 'Text ca'], $defaults, 'es'));
        $this->assertSame('Default es', TranslatedText::resolve([], $defaults, 'es'));
        $this->assertSame('Default ca', TranslatedText::resolve([], $defaults, 'fr'));
    }

    public function testTheGeneralTabSavesTheLanguagesAndRefusesUnsupportedOnes(): void
    {
        $this->loginWith('settings.update', 'settings.core');
        $form = ['Core-siteName' => '', 'Core-timezone' => 'UTC', 'Core-pwaStart' => 'public', 'Core-widgetMaxLinks' => '20'];

        // Checkboxes (D-31-01): every language checked by default, at least one required.
        $page = $this->send('GET', 'backend/settings/core');
        $body = (string) $page->response()->getBody();
        $this->assertSame(3, substr_count($body, 'name="Core-locales[]"'));
        $this->assertSame(3, preg_match_all('/name="Core-locales\[\]" value="(?:ca|es|en)" checked/', $body));

        $this->send('POST', 'backend/settings/core', $form + ['Core-defaultLocale' => 'es'])->assertStatus(422);
        $this->send('POST', 'backend/settings/core', $form + ['Core-defaultLocale' => 'es', 'Core-locales' => 'es'])->assertStatus(422);
        $this->send('POST', 'backend/settings/core', $form + ['Core-defaultLocale' => 'es', 'Core-locales' => ['fr', 'xx']])->assertStatus(422);
        $this->assertSame(0, $this->storedCount('Core', 'locales'));

        $this->send('POST', 'backend/settings/core', $form + ['Core-defaultLocale' => 'es', 'Core-locales' => ['es', 'ca', 'fr', ['x']]])
            ->assertRedirectTo(site_url('backend/settings/core'));

        $this->assertSame(['ca', 'es'], setting('Core.locales'), 'Unknown values dropped, in the order of the options.');
        $this->assertSame(['ca', 'es'], Locales::available());
        $this->assertSame('es', Locales::default());

        $page = $this->send('GET', 'backend/settings/core');
        $this->assertStringContainsString('value="en">', (string) $page->response()->getBody(), 'English is shown unchecked.');

        $this->send('POST', 'backend/settings/core', $form + ['Core-defaultLocale' => 'es', 'Core-locales' => ['ca', 'es', 'en']]);
        $this->assertSame(0, $this->storedCount('Core', 'locales'), 'Every language checked again: the default, nothing stored.');
    }
}
