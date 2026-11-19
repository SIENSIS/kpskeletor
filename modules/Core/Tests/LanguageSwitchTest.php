<?php

declare(strict_types=1);

namespace Modules\Core\Tests;

use CodeIgniter\Security\Exceptions\SecurityException;
use Config\App;
use Config\Services;
use Modules\Auth\Tests\AuthTestCase;
use Modules\Core\I18n\LocaleNegotiator;

/**
 * The public language switch (docs/i18n.md, "Choosing the language"): the default theme's header
 * offers the available languages; `POST language` stores the choice in the `kp_locale` cookie
 * (which wins over the browser), saves it as a signed-in user's profile language, refuses
 * languages the site does not offer and only goes back to internal paths.
 *
 * @internal
 */
final class LanguageSwitchTest extends AuthTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(App::class)->defaultLocale = 'ca';
        service('theme')->reset();
        Services::resetSingle('redirectresponse'); // shared: it would keep a previous test's cookie
        $this->withHeaders(['Accept-Language' => 'en']);
    }

    protected function tearDown(): void
    {
        Services::resetSingle('settings');
        Services::resetSingle('language');
        parent::tearDown();
    }

    public function testThePublicHeaderOffersTheAvailableLanguages(): void
    {
        $html = $this->html('/');

        $this->assertStringContainsString('<html lang="en"', $html, 'The browser\'s language before any choice.');
        $this->assertStringContainsString('action="' . site_url('language') . '"', $html);

        $this->assertSame(3, substr_count($html, '<input type="hidden" name="return" value="/">'), 'Back to this page.');

        foreach (['ca' => 'Català', 'es' => 'Español', 'en' => 'English'] as $locale => $name) {
            $this->assertStringContainsString('<input type="hidden" name="locale" value="' . $locale . '">', $html);
            $this->assertMatchesRegularExpression('/<button type="submit" class="dropdown-item[^"]*" lang="' . $locale . '"[^>]*>' . $name . '<\/button>/', $html);
        }
    }

    public function testTheSwitchIsHiddenWithOneLanguageOrWhenTheThemeTurnsItOff(): void
    {
        setting('Core.locales', ['ca']);
        $this->assertStringNotContainsString('language-switch', $this->html('/'));

        setting('Core.locales', ['ca', 'es', 'en']);
        service('settings')->set('Theme.languageSwitch', false, 'theme:default');
        service('theme')->reset();
        $this->assertStringNotContainsString('language-switch', $this->html('/'));
    }

    public function testTheChoiceIsStoredInACookieThatWinsOverTheBrowser(): void
    {
        $this->send('POST', 'language', ['locale' => 'es', 'return' => '/'])->assertRedirectTo(rtrim(site_url('/'), '/'));

        $this->assertSame('es', $this->cookies[LocaleNegotiator::COOKIE] ?? null);
        $this->assertStringContainsString('<html lang="es"', $this->html('/'), 'The chosen language, not the browser\'s English.');
    }

    public function testALanguageTheSiteDoesNotOfferIsIgnored(): void
    {
        setting('Core.locales', ['ca', 'en']);

        foreach (['es', 'fr', 'xx', '../ca'] as $locale) {
            $this->send('POST', 'language', ['locale' => $locale, 'return' => '/'])->assertRedirect();
            $this->assertArrayNotHasKey(LocaleNegotiator::COOKIE, $this->cookies, $locale);
        }

        // A stored choice the site no longer offers falls back to the browser.
        $this->cookies[LocaleNegotiator::COOKIE] = 'es';
        $this->assertStringContainsString('<html lang="en"', $this->html('/'));
    }

    public function testItOnlyGoesBackToInternalPaths(): void
    {
        $this->send('POST', 'language', ['locale' => 'es', 'return' => '/login?x=1'])->assertRedirectTo(site_url('login?x=1'));

        foreach (['https://evil.example/', '//evil.example/x', 'javascript:alert(1)'] as $return) {
            $this->send('POST', 'language', ['locale' => 'es', 'return' => $return])->assertRedirectTo(rtrim(site_url('/'), '/'));
        }
    }

    public function testAForgedPostWithoutTheCsrfTokenChangesNothing(): void
    {
        try {
            $this->send('POST', 'language', ['locale' => 'es', 'return' => '/', 'csrf_test_name' => 'wrong']);
            $this->fail('The CSRF filter refuses it.');
        } catch (SecurityException) {
            // refused before the controller
        }

        $this->assertArrayNotHasKey(LocaleNegotiator::COOKIE, $this->cookies);
    }

    public function testASignedInUsersChoiceBecomesTheirProfileLanguage(): void
    {
        $user = $this->createUser('ana@example.com', 'admin');
        $this->login('ana@example.com');

        $this->send('POST', 'language', ['locale' => 'es', 'return' => '/'])->assertRedirect();

        $this->assertSame('es', db_connect()->table('user_profiles')->where('user_id', $user->id)->get()->getRowArray()['locale'] ?? null);
        $this->assertStringContainsString('<html lang="es"', $this->html('backend/profile'), 'The back office follows the profile.');
    }

    private function html(string $path): string
    {
        return html_entity_decode((string) $this->send('GET', $path)->response()->getBody(), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}
