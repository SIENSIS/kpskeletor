<?php

declare(strict_types=1);

namespace Modules\Pages\Tests;

use CodeIgniter\I18n\Time;

/**
 * The public pages: nested slugs, statuses and scheduling, private pages (login and the right
 * group), noindex, the home page, templates, route priority and the sitemap.
 *
 * @internal
 */
final class PublicPagesTest extends PagesTestCase
{
    public function testPublishedPublicPagesAtTheirNestedSlugs(): void
    {
        $about = $this->page('Qui som', [], ['es' => ['title' => 'Quiénes somos', 'slug' => 'quienes-somos']]);
        $this->page('Equip', ['parent_id' => $about]);

        $this->assertStringContainsString('Text de Qui som', $this->body('GET', 'qui-som'));
        $this->assertStringContainsString('Text de Equip', $this->body('GET', 'qui-som/equip'));
        // The Spanish slug finds the page too; the texts follow the visitor's language (en → Catalan
        // fallback) and the canonical URL is the path in that language.
        $spanish = $this->body('GET', 'quienes-somos');
        $this->assertStringContainsString('Text de Qui som', $spanish);
        $this->assertStringContainsString('<link rel="canonical" href="' . site_url('qui-som') . '">', $spanish);
        $this->assertStringContainsString('Text de Equip', $this->body('GET', 'quienes-somos/equip'));

        $this->assertNotFound('GET', 'equip');
        $this->assertNotFound('GET', 'qui-som/no-existeix');
        $this->assertNotFound('GET', 'no-existeix');

        $page = $this->body('GET', 'qui-som/equip');
        $this->assertStringContainsString('<link rel="canonical" href="' . site_url('qui-som/equip') . '">', $page);
        $this->assertStringNotContainsString('noindex', $page);
    }

    public function testDraftsTrashedAndFuturePagesAreNotFound(): void
    {
        $this->page('Esborrany', ['status' => 'draft', 'publish_at' => null]);
        $this->page('Futura', ['status' => 'scheduled', 'publish_at' => Time::now()->addDays(1)->toDateTimeString()]);
        $this->page('Ja és hora', ['status' => 'scheduled', 'publish_at' => Time::now()->subMinutes(1)->toDateTimeString()]);
        $trashed = $this->page('Paperera', ['deleted_at' => Time::now()->toDateTimeString()]);
        $this->page('Fill de la paperera', ['parent_id' => $trashed]);

        $this->assertNotFound('GET', 'esborrany');
        $this->assertNotFound('GET', 'futura');
        $this->assertNotFound('GET', 'paperera');
        $this->assertNotFound('GET', 'paperera/fill-de-la-paperera');
        $this->assertStringContainsString('Text de Ja és hora', $this->body('GET', 'ja-es-hora'), 'A scheduled page shows once its date passes.');
    }

    public function testPrivatePagesRequireLoginAndTheRightGroup(): void
    {
        $this->page('Intranet', ['visibility' => 'private']);
        $this->page('Editors', ['visibility' => 'groups'], [], ['editor']);

        $this->send('GET', 'intranet')->assertRedirectTo(site_url('login'));
        $this->assertSame(site_url('intranet'), session()->getTempdata('beforeLoginUrl'), 'Back to the page after the login.');
        $this->send('GET', 'editors')->assertRedirectTo(site_url('login'));

        $this->createUser('ana@example.com', 'user');
        $this->login('ana@example.com');
        $response = $this->send('GET', 'intranet');
        $response->assertOK();
        $this->assertStringContainsString('no-store', $response->response()->getHeaderLine('Cache-Control'));
        $this->assertStringContainsString('<meta name="robots" content="noindex, nofollow">', (string) $response->response()->getBody());
        $this->assertNotFound('GET', 'editors');

        $this->createUser('ed@example.com', 'editor');
        $this->login('ed@example.com');
        $this->assertStringContainsString('Text de Editors', $this->body('GET', 'editors'));
    }

    public function testNoindexPages(): void
    {
        $this->page('Avís legal', ['noindex' => 1]);

        $this->assertStringContainsString('<meta name="robots" content="noindex, nofollow">', $this->body('GET', 'avis-legal'));
    }

    public function testTheHomePageFromTheSettings(): void
    {
        $home    = $this->page('Inici', [], ['en' => ['title' => 'Home', 'slug' => 'home']]);
        $private = $this->page('Privada', ['visibility' => 'private']);
        $trashed = $this->page('Paperera', ['deleted_at' => Time::now()->toDateTimeString()]);

        $this->assertStringNotContainsString('Text de Inici', $this->body('GET', '/'), 'No setting: the theme\'s home.');

        service('settings')->set('Pages.homePage', (string) $home);
        $page = $this->body('GET', '/');
        $this->assertStringContainsString('Home', $page, 'In the visitor\'s language (en).');
        $this->assertStringContainsString('<link rel="canonical" href="' . site_url('/') . '">', $page);
        $this->send('GET', 'inici')->assertRedirectTo(site_url('/'));

        // The setting stores the id: renaming the slug keeps the home page (D-20-01).
        db_connect()->table('page_translations')->where('page_id', $home)->where('locale', 'ca')->update(['slug' => 'portada']);
        $this->assertStringContainsString('Home', $this->body('GET', '/'));

        service('settings')->set('Pages.homePage', (string) $private);
        $this->assertStringNotContainsString('Text de Privada', $this->body('GET', '/'), 'Guests do not see a private home page.');

        foreach ([(string) $trashed, '999', 'inici'] as $value) {
            service('settings')->set('Pages.homePage', $value);
            $this->assertStringNotContainsString('Text de', $this->body('GET', '/'), "{$value}: the theme's home.");
        }
    }

    public function testTemplates(): void
    {
        $parent = $this->page('Secció', ['template' => 'sidebar']);
        $this->page('Subpàgina pública', ['parent_id' => $parent]);
        $this->page('Subpàgina privada', ['parent_id' => $parent, 'visibility' => 'private']);
        $this->page('Desconeguda', ['template' => 'no-existeix']);

        $page = $this->body('GET', 'seccio');
        $this->assertStringContainsString('Subpàgina pública', $page);
        $this->assertStringNotContainsString('Subpàgina privada', $page, 'Only the subpages the visitor may read.');
        $this->assertStringContainsString('Text de Desconeguda', $this->body('GET', 'desconeguda'), 'An unknown template falls back to the default.');
    }

    public function testOtherRoutesWinOverPages(): void
    {
        db_connect()->table('pages')->insert(['status' => 'published', 'publish_at' => Time::now()->subHours(1)->toDateTimeString(), 'visibility' => 'public', 'template' => 'default']);
        db_connect()->table('page_translations')->insert(['page_id' => db_connect()->insertID(), 'locale' => 'ca', 'title' => 'Pàgina amagada', 'slug' => 'news', 'body' => '<p>Amagada</p>']);

        $this->assertStringNotContainsString('Amagada', $this->body('GET', 'news'), 'The News route wins.');
        $this->assertNotFound('GET', 'backend/no-existeix'); // the router's 404: never a page
    }

    public function testTheSitemapListsOnlyPublicIndexablePages(): void
    {
        $about = $this->page('Qui som', [], ['es' => ['title' => 'Quiénes somos', 'slug' => 'quienes-somos']]);
        $this->page('Equip', ['parent_id' => $about]);
        $this->page('Privada', ['visibility' => 'private']);
        $this->page('De grup', ['visibility' => 'groups'], [], ['user']);
        $this->page('Esborrany', ['status' => 'draft', 'publish_at' => null]);
        $this->page('Futura', ['status' => 'scheduled', 'publish_at' => Time::now()->addDays(1)->toDateTimeString()]);
        $this->page('Avís legal', ['noindex' => 1]);
        $this->page('Còpia', [], ['es' => ['title' => 'Copia', 'slug' => 'copia', 'canonical' => 'https://example.org/original']]);
        $this->createUser('ana@example.com', 'user');
        $this->login('ana@example.com');

        $response = $this->send('GET', 'sitemap.xml');
        $this->assertStringContainsString('application/xml', $response->response()->getHeaderLine('Content-Type'));
        $xml = simplexml_load_string((string) $response->response()->getBody());
        $this->assertNotFalse($xml, 'Well-formed XML.');

        $urls = [];

        foreach ($xml->url as $url) {
            $urls[] = (string) $url->loc;
        }

        $this->assertSame([site_url('/'), site_url('qui-som'), site_url('quienes-somos'), site_url('copia'), site_url('qui-som/equip')], $urls, 'Even for a signed-in visitor.');
    }
}
