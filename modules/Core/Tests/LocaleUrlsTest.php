<?php

declare(strict_types=1);

namespace Modules\Core\Tests;

use Config\App;
use Config\Services;
use Modules\Auth\Tests\AuthTestCase;

/**
 * The language of public URLs (D-37-02, docs/i18n.md "Language in the URL"): a public path
 * without a prefix is in the site's default language, `/es/…` in Spanish; internal links keep the
 * prefix; the language switch links to the same page in every language; `/ca/…` (the default),
 * languages the site does not offer and a prefix in front of a non-public path redirect; the back
 * office and sign-in pages keep negotiating (profile, browser, default).
 *
 * @internal
 */
final class LocaleUrlsTest extends AuthTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(App::class)->defaultLocale = 'ca';
        service('theme')->reset();
    }

    protected function tearDown(): void
    {
        Services::resetSingle('settings');
        Services::resetSingle('language');
        parent::tearDown();
    }

    public function testAPathWithoutPrefixIsInTheDefaultLanguageWhateverTheBrowserSays(): void
    {
        $html = $this->withHeaders(['Accept-Language' => 'es'])->html('/');

        $this->assertStringContainsString('<html lang="ca"', $html);
    }

    public function testThePrefixGivesTheLanguageAndInternalLinksKeepIt(): void
    {
        $html = $this->html('es');

        $this->assertStringContainsString('<html lang="es"', $html);
        $this->assertMatchesRegularExpression('#class="brand[^"]*" href="' . preg_quote(service('localeurls')->url('', 'es'), '#') . '"#', $html, 'The brand links to the Spanish home page.');
        $this->assertSame(site_url('es/news'), site_url('news'), 'site_url() keeps the prefix of the current public page.');
        $this->assertSame(site_url('backend'), rtrim(site_url('/'), '/es') . '/backend', 'Never on non-public paths.');
        $this->assertSame('https://example.org/x', service('localeurls')->localize('https://example.org/x'), 'Absolute URLs untouched.');
        $this->assertSame('en/news', service('localeurls')->localize('en/news'), 'A path that already has a language.');
    }

    public function testTheSwitchLinksToTheSamePageInEveryLanguage(): void
    {
        $html  = $this->html('es');
        $urls  = service('localeurls');
        $links = [];

        preg_match_all('/<a class="dropdown-item[^"]*" href="([^"]+)" hreflang="([a-z]+)"/', $html, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $links[$match[2]] = $match[1];
        }

        $this->assertSame(['ca' => $urls->url('', 'ca'), 'es' => $urls->url('', 'es'), 'en' => $urls->url('', 'en')], $links);
        $this->assertStringContainsString('<link rel="alternate" hreflang="en" href="' . $urls->url('', 'en') . '">', $html);
        $this->assertStringContainsString('<link rel="alternate" hreflang="x-default" href="' . $urls->url('', 'ca') . '">', $html);
    }

    public function testTheSwitchIsHiddenWithOneLanguageOrWhenTheThemeTurnsItOff(): void
    {
        setting('Core.locales', ['ca']);
        $this->assertStringNotContainsString('language-switch', $this->html('/'));
        $this->assertStringNotContainsString('hreflang', $this->html('/'));

        setting('Core.locales', ['ca', 'es', 'en']);
        service('settings')->set('Theme.languageSwitch', false, 'theme:default');
        service('theme')->reset();
        $this->assertStringNotContainsString('language-switch', $this->html('/'));
    }

    public function testRedirectsOfPrefixesThatAreNotNeeded(): void
    {
        $urls = service('localeurls');

        $this->send('GET', 'ca/news')->assertRedirectTo($urls->url('news', 'ca'));
        $this->send('GET', 'es/login')->assertRedirectTo($urls->url('login', 'ca'));
        $this->send('GET', 'es/backend')->assertRedirectTo($urls->url('backend', 'ca'));

        setting('Core.locales', ['ca', 'en']);
        $this->send('GET', 'es/news')->assertRedirectTo($urls->url('news', 'ca'));
    }

    public function testTheBackOfficeAndSignInPagesKeepNegotiating(): void
    {
        $this->assertStringContainsString('<html lang="es"', $this->withHeaders(['Accept-Language' => 'es'])->html('login'));

        $user = $this->createUser('ana@example.com', 'admin');
        db_connect()->table('user_profiles')->where('user_id', $user->id)->delete();
        db_connect()->table('user_profiles')->insert(['user_id' => $user->id, 'name' => 'Ana', 'locale' => 'en']);
        $this->login('ana@example.com');
        $this->withHeaders([]);

        $this->assertStringContainsString('<html lang="en"', $this->html('backend/profile'), 'The profile language in the back office.');
        $this->assertStringContainsString('<html lang="es"', $this->html('es'), 'The URL\'s language on the public site, even signed in.');
    }

    public function testItCanBeTurnedOff(): void
    {
        config(\Config\LocaleUrls::class)->enabled = false;
        Services::resetSingle('localeurls');

        $html = $this->withHeaders(['Accept-Language' => 'es'])->html('/');
        $this->assertStringContainsString('<html lang="es"', $html, 'The browser\'s language again.');
        $this->assertStringNotContainsString('language-switch', $html);
        $this->expectException(\CodeIgniter\Exceptions\PageNotFoundException::class); // no prefixes
        $this->send('GET', 'es/news');
    }

    private function html(string $path): string
    {
        return html_entity_decode((string) $this->send('GET', $path)->response()->getBody(), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}
