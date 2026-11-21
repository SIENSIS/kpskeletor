<?php

declare(strict_types=1);

namespace Modules\Pages\Tests;

use CodeIgniter\Config\Services;
use CodeIgniter\I18n\Time;
use Modules\Core\Modules\CacheInvalidator;
use Modules\Core\Modules\ModuleDiscovery;
use Modules\Core\Registry\DeclarationRegistry;
use Modules\Core\Sections\SectionData;
use Modules\Core\Tests\StorageFixturesTrait;
use Modules\Pages\Services\PageSections;

/**
 * Section types of session 46 (D-46-01 … D-46-03): embeds (allowed hosts, the warning and its
 * link, CSP per page, click to load), carousel (no rotation by default), testimonials and logos,
 * the news list (visibility, categories, two paged lists on one page, search) and the events
 * sections (public events only, the public calendar switch).
 *
 * @internal
 */
final class ModuleSectionsTest extends PagesTestCase
{
    use StorageFixturesTrait;

    protected function setUp(): void
    {
        parent::setUp();
        service('routes')->loadRoutes();
        $manifests = [];

        foreach ((new ModuleDiscovery())->discover() as $name => $module) {
            if ($module->isValid() && $module->manifest !== null) {
                $manifests[$name] = $module->manifest;
            }
        }

        $registry = new DeclarationRegistry();
        $registry->store($registry->build($manifests));
        (new CacheInvalidator())->invalidate(['menus', 'modules']);
        $this->makeStorage();
    }

    protected function tearDown(): void
    {
        service('settings')->forget('Pages.embedHosts');
        config(\Modules\Events\Config\Calendar::class)->publicCalendar = true;
        $this->removeStorageRoot();
        parent::tearDown();
    }

    /**
     * @param array<string, mixed>                $values
     * @param array<string, array<string, mixed>> $texts
     * @param array<string, mixed>                $options
     */
    private function section(int $pageId, string $type, array $values, array $texts = [], array $options = []): int
    {
        return (new PageSections())->save($pageId, new SectionData($type, $values, $texts, $options));
    }

    private function cspOf(string $path): string
    {
        Services::resetSingle('csp');
        $response = $this->send('GET', $path)->response();
        $response->getCSP()->finalize($response); // Response::send() does it; feature tests do not send

        return $response->getHeaderLine('Content-Security-Policy');
    }

    /**
     * The text of a language key, in whatever language the screen uses (ca, es or en), is in the body.
     *
     * @param list<string> $args
     */
    private function assertTranslated(string $key, array $args, string $body, string $message = ''): void
    {
        $found = array_filter(['ca', 'es', 'en'], static fn (string $locale): bool => str_contains($body, lang($key, $args, $locale)));

        $this->assertNotEmpty($found, $message !== '' ? $message : 'The text of ' . $key . ' is shown.');
    }

    /** Back to a guest: an empty session and no cookies. */
    private function logout(): void
    {
        $_SESSION      = [];
        $this->cookies = [];
    }

    private function admin(): void
    {
        $this->createUser('ad@example.com', 'admin');
        $this->login('ad@example.com');
    }

    /**
     * @return array<string, mixed>
     */
    private function embedPost(string $url, string $title = 'Visita virtual'): array
    {
        return ['type' => 'pages.embed', 'f' => ['url' => $url, 'title' => ['ca' => $title], 'ratio' => '16x9', 'click_to_load' => '1', 'fullscreen' => '1']];
    }

    // --- Embeds (D-46-01) --------------------------------------------------------------------

    public function testAnEmbedOfAHostNotAllowedIsRefusedWithAMessageThatNamesIt(): void
    {
        $page = $this->page('Visita', ['layout' => 'sections']);
        $this->editor();

        $response = $this->send('POST', 'backend/pages/' . $page . '/sections/save', $this->embedPost('https://tour.example.org/360'));
        $this->assertSame(422, $response->response()->getStatusCode());
        $body = html_entity_decode((string) $response->response()->getBody(), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $this->assertStringContainsString('tour.example.org', $body, 'The message names the host.');
        $this->assertStringContainsString(lang('Pages.embed.errors.host', ['tour.example.org']), $body);
        $this->assertStringNotContainsString('#setting-Pages-embedHosts', $body, 'No link for users without settings.security.');
        $this->assertSame(0, (new PageSections())->count($page));

        // Not https, credentials, nonsense: refused too.
        service('settings')->set('Pages.embedHosts', ['tour.example.org']);

        foreach (['http://tour.example.org/360', 'https://user:pw@tour.example.org/', 'javascript:alert(1)', '//tour.example.org/x', 'https://tour.example.org/a b'] as $bad) {
            $this->assertSame(422, $this->send('POST', 'backend/pages/' . $page . '/sections/save', $this->embedPost($bad))->response()->getStatusCode(), $bad);
        }

        $this->assertSame(0, (new PageSections())->count($page));
    }

    public function testTheSettingsLinkIsShownOnlyToSecurityAdministrators(): void
    {
        $page = $this->page('Visita', ['layout' => 'sections']);
        $this->admin();

        $body = $this->body('POST', 'backend/pages/' . $page . '/sections/save', $this->embedPost('https://tour.example.org/360'));
        $this->assertStringContainsString('settings/security#setting-Pages-embedHosts', $body);
    }

    public function testAnAllowedEmbedLoadsOnClickAndOpensTheCspOnlyOnItsPage(): void
    {
        service('settings')->set('Pages.embedHosts', ['www.youtube-nocookie.com', '*.tour.example.org']);
        $page  = $this->page('Visita', ['layout' => 'sections']);
        $other = $this->page('Altra', ['layout' => 'sections']);
        $this->section($other, 'pages.text', ['column' => 'narrow'], ['ca' => ['heading' => '', 'text' => '<p>Res</p>']]);
        $this->editor();

        $this->assertTrue($this->send('POST', 'backend/pages/' . $page . '/sections/save', $this->embedPost('https://www.youtube-nocookie.com/embed/abc', 'Vídeo'))->isRedirect());
        $this->assertTrue($this->send('POST', 'backend/pages/' . $page . '/sections/save', $this->embedPost('https://360.tour.example.org/escola'))->isRedirect(), 'A subdomain of a *. entry.');
        $this->assertSame(2, (new PageSections())->count($page));
        $this->logout();

        $body = $this->body('GET', 'visita');
        $this->assertStringContainsString('data-component="pages/embed"', $body);
        $this->assertStringContainsString('data-src="https://www.youtube-nocookie.com/embed/abc"', $body);
        $this->assertStringNotContainsString('<iframe', $body, 'Click to load: no request to the other site before the click.');

        $csp = $this->cspOf('visita');
        $this->assertMatchesRegularExpression('#frame-src [^;]*https://www\.youtube-nocookie\.com#', $csp);
        $this->assertMatchesRegularExpression('#frame-src [^;]*https://360\.tour\.example\.org#', $csp);
        $this->assertStringNotContainsString('https://*.tour.example.org', $csp, 'Only the origins the page uses.');
        $this->assertStringNotContainsString('youtube-nocookie', $this->cspOf('altra'), 'Another page does not get the origin.');
    }

    public function testWithoutClickToLoadTheIframeIsSandboxed(): void
    {
        service('settings')->set('Pages.embedHosts', ['www.youtube-nocookie.com']);
        $page = $this->page('Video', ['layout' => 'sections']);
        $this->section($page, 'pages.embed', ['url' => 'https://www.youtube-nocookie.com/embed/abc', 'ratio' => '4x3', 'click_to_load' => false, 'fullscreen' => false], ['ca' => ['heading' => '', 'intro' => '', 'title' => 'Vídeo de prova']]);

        $body = $this->body('GET', 'video');
        $this->assertMatchesRegularExpression('#<iframe src="https://www\.youtube-nocookie\.com/embed/abc" title="Vídeo de prova" loading="lazy" sandbox="allow-scripts allow-same-origin allow-popups allow-presentation" referrerpolicy="strict-origin-when-cross-origin">#', $body);
        $this->assertStringContainsString('ratio-4x3', $body);
    }

    public function testAnEmbedWhoseHostWasRemovedShowsAWarningAndNothingOnThePublicPage(): void
    {
        service('settings')->set('Pages.embedHosts', ['www.youtube-nocookie.com']);
        $page    = $this->page('Video', ['layout' => 'sections']);
        $section = $this->section($page, 'pages.embed', ['url' => 'https://www.youtube-nocookie.com/embed/abc', 'ratio' => '16x9', 'click_to_load' => true, 'fullscreen' => true], ['ca' => ['heading' => 'Mireu-lo', 'intro' => '', 'title' => 'Vídeo']]);
        service('settings')->set('Pages.embedHosts', []);

        $public = $this->body('GET', 'video');
        $this->assertStringNotContainsString('data-component="pages/embed"', $public);
        $this->assertStringNotContainsString('Mireu-lo', $public, 'The whole section is left out.');
        $this->assertStringNotContainsString('youtube-nocookie', $this->cspOf('video'));

        $this->admin();
        $this->assertTranslated('Pages.embed.errors.removed', ['www.youtube-nocookie.com'], $this->body('GET', 'backend/pages/' . $page . '/sections'), 'The editor list warns.');
        $form = $this->body('GET', 'backend/pages/' . $page . '/sections/' . $section);
        $this->assertTranslated('Pages.embed.errors.removed', ['www.youtube-nocookie.com'], $form, 'The form warns.');
        $this->assertStringContainsString('#setting-Pages-embedHosts', $form, 'With the link for settings.security.');
        $this->assertTranslated('Pages.embed.errors.removed', ['www.youtube-nocookie.com'], $this->body('GET', 'backend/pages/preview/' . $this->code($page)), 'The preview warns.');
    }

    // --- Carousel (D-46-02), testimonials and logos --------------------------------------------

    public function testTheCarouselDoesNotRotateByDefault(): void
    {
        $file = service('storage')->storeContents(self::png(16, 9), 'slide.png', 'public', 'modules/pages');
        $page = $this->page('Inici', ['layout' => 'sections']);
        $this->editor();

        $post = ['type' => 'pages.carousel', 'f' => [
            'label'  => ['ca' => 'Novetats'],
            'slides' => [
                ['image' => ['key' => $file->key, 'decorative' => '1'], 'title' => ['ca' => 'Primera'], 'text' => ['ca' => ''], 'link' => ['type' => '']],
                ['image' => ['key' => $file->key, 'decorative' => '1'], 'title' => ['ca' => 'Segona'], 'text' => ['ca' => ''], 'link' => ['type' => '']],
            ],
        ]];
        $this->assertTrue($this->send('POST', 'backend/pages/' . $page . '/sections/save', $post)->isRedirect());
        $this->logout();

        $body = $this->body('GET', 'inici');
        $this->assertStringContainsString('data-component="pages/carousel" data-rotate="0"', $body);
        $this->assertStringNotContainsString('data-carousel-pause', $body, 'No pause button without rotation.');
        $this->assertStringContainsString('aria-roledescription="' . lang('Pages.carousel.roledescription') . '"', $body);
        $this->assertSame(2, preg_match_all('/data-carousel-slide[ >]/', $body), 'Every slide is in the page (without JavaScript they stack).');

        // Rotation: only when turned on, never faster than 6 s, always with a pause button.
        $section = (new PageSections())->all($page)[0];
        $this->login('ed@example.com');
        $post['id'] = (string) $section->id;
        $post['f']['rotate'] = '1';
        $post['f']['interval'] = '3';
        $this->assertSame(422, $this->send('POST', 'backend/pages/' . $page . '/sections/save', $post)->response()->getStatusCode());
        $post['f']['interval'] = '7';
        $this->assertTrue($this->send('POST', 'backend/pages/' . $page . '/sections/save', $post)->isRedirect());
        $this->logout();
        $body = $this->body('GET', 'inici');
        $this->assertStringContainsString('data-rotate="1" data-interval="7"', $body);
        $this->assertStringContainsString('data-carousel-pause', $body);
    }

    public function testTestimonialsAndLogos(): void
    {
        $logo = service('storage')->storeContents(self::png(16, 9), 'logo.png', 'public', 'modules/pages');
        $page = $this->page('Entitats', ['layout' => 'sections']);
        $this->editor();

        // A logo needs its alternative text: "decorative" is not offered and a forged one is ignored.
        $post = ['type' => 'pages.logos', 'f' => ['logos' => [['image' => ['key' => $logo->key, 'decorative' => '1', 'alt' => ['ca' => '']], 'link' => ['type' => '']]]]];
        $this->assertSame(422, $this->send('POST', 'backend/pages/' . $page . '/sections/save', $post)->response()->getStatusCode());
        $post['f']['logos'][0]['image']['alt'] = ['ca' => 'Ajuntament'];
        $post['f']['logos'][0]['link'] = ['type' => 'url', 'value' => ['url' => 'https://ajuntament.example.org/']];
        $post['f']['grayscale'] = '1';
        $this->assertTrue($this->send('POST', 'backend/pages/' . $page . '/sections/save', $post)->isRedirect());
        $this->assertStringNotContainsString('-decorative"', $this->body('GET', 'backend/pages/' . $page . '/sections/new/pages.logos'));

        $quote = ['type' => 'pages.testimonials', 'f' => ['heading' => ['ca' => 'Què en diuen'], 'items' => [['photo' => ['key' => ''], 'quote' => ['ca' => 'Una escola <b>fantàstica</b>.'], 'name' => 'Anna', 'role' => ['ca' => 'Mare']]], 'columns' => '3']];
        $this->assertTrue($this->send('POST', 'backend/pages/' . $page . '/sections/save', $quote)->isRedirect());
        $this->logout();

        $body = $this->body('GET', 'entitats');
        $this->assertStringContainsString('kp-logos-gray', $body);
        $this->assertStringContainsString('alt="Ajuntament"', $body);
        $this->assertStringContainsString('href="https://ajuntament.example.org/"', $body);
        $this->assertStringContainsString('Una escola <b>fantàstica</b>.', $body);
        $this->assertStringNotContainsString('<b>fantàstica', (string) $this->send('GET', 'entitats')->response()->getBody(), 'Quotes are escaped.');
        $this->assertStringContainsString('kp-bg-dark', $body, 'Testimonials are a dark band by default.');
    }

    // --- News list ------------------------------------------------------------------------------

    private function newsCategory(string $slug, string $name): int
    {
        $now = Time::now()->toDateTimeString();
        db_connect()->table('news_categories')->insert(['slug' => $slug, 'position' => 0, 'active' => 1, 'created_at' => $now, 'updated_at' => $now]);
        $id = (int) db_connect()->insertID();
        db_connect()->table('news_category_translations')->insert(['category_id' => $id, 'locale' => 'ca', 'name' => $name, 'description' => '']);

        return $id;
    }

    /**
     * @param array<string, mixed> $values
     * @param list<string>         $groups
     */
    private function newsPost(string $title, array $values = [], array $groups = []): int
    {
        $now = Time::now();
        db_connect()->table('news')->insert($values + [
            'slug' => url_title(strtolower($title), '-', true), 'type' => 'article', 'priority' => 'info', 'pinned' => 0,
            'visibility' => 'public', 'status' => 'published', 'published_at' => $now->subHours(1)->toDateTimeString(),
            'created_at' => $now->toDateTimeString(), 'updated_at' => $now->toDateTimeString(),
        ]);
        $id = (int) db_connect()->insertID();
        db_connect()->table('news_translations')->insert(['news_id' => $id, 'locale' => 'ca', 'title' => $title, 'summary' => 'Resum ' . $title, 'body' => '<p>x</p>']);

        foreach ($groups as $group) {
            $row = db_connect()->table('groups')->where('name', $group)->get()->getRowArray();
            db_connect()->table('news_groups')->insert(['news_id' => $id, 'group_id' => (int) ($row['id'] ?? 0)]);
        }

        return $id;
    }

    /**
     * @param list<int> $categories
     *
     * @return array<string, mixed>
     */
    private static function newsValues(string $mode, int $limit, array $categories = [], bool $search = false): array
    {
        return ['mode' => $mode, 'limit' => $limit, 'categories' => array_map('strval', $categories), 'columns' => '4', 'show_date' => true, 'show_summary' => true, 'show_image' => true, 'search' => $search, 'more' => null];
    }

    public function testTheNewsListShowsWhatThePublicListShows(): void
    {
        $sortides = $this->newsCategory('sortides', 'Sortides');
        $altres   = $this->newsCategory('altres', 'Altres');
        $this->newsPost('Sortida al museu', ['category_id' => $sortides]);
        $this->newsPost('Sortida privada', ['category_id' => $sortides, 'visibility' => 'private']);
        $this->newsPost('Sortida del grup', ['category_id' => $sortides, 'visibility' => 'groups'], ['editor']);
        $this->newsPost('Sortida futura', ['category_id' => $sortides, 'published_at' => Time::now()->addDays(2)->toDateTimeString()]);
        $this->newsPost('Sortida caducada', ['category_id' => $sortides, 'expires_at' => Time::now()->subHours(1)->toDateTimeString()]);
        $this->newsPost('Esborrany', ['category_id' => $sortides, 'status' => 'draft']);
        $this->newsPost('Una altra notícia', ['category_id' => $altres]);

        $page = $this->page('Sortides', ['layout' => 'sections']);
        $this->section($page, 'news.list', self::newsValues('latest', 10, [$sortides]), ['ca' => ['heading' => 'Les sortides', 'intro' => '']]);

        foreach ([null, 'ed@example.com'] as $user) {
            if ($user !== null) {
                $this->editor();
            }

            $body = $this->body('GET', 'sortides');
            $this->assertStringContainsString('Sortida al museu', $body);

            foreach (['Sortida privada', 'Sortida del grup', 'Sortida futura', 'Sortida caducada', 'Esborrany', 'Una altra notícia'] as $hidden) {
                $this->assertStringNotContainsString($hidden, $body, $hidden . ' (' . ($user ?? 'guest') . ')');
            }
        }

        // The category's choices come from the categories; a forged one is refused.
        $response = $this->send('POST', 'backend/pages/' . $page . '/sections/save', ['type' => 'news.list', 'f' => ['mode' => 'latest', 'limit' => '4', 'categories' => ['999']]]);
        $this->assertSame(422, $response->response()->getStatusCode());
        $this->assertStringContainsString('value="' . $sortides . '"', $this->body('GET', 'backend/pages/' . $page . '/sections/new/news.list'));
    }

    public function testTwoPagedListsOnOnePageDoNotClash(): void
    {
        $a = $this->newsCategory('a', 'A');
        $b = $this->newsCategory('b', 'B');

        for ($i = 1; $i <= 5; $i++) {
            $this->newsPost('Notícia A' . $i, ['category_id' => $a, 'published_at' => Time::now()->subHours(10 - $i)->toDateTimeString()]);
            $this->newsPost('Notícia B' . $i, ['category_id' => $b, 'published_at' => Time::now()->subHours(10 - $i)->toDateTimeString()]);
        }

        $page = $this->page('Totes', ['layout' => 'sections']);
        $one  = $this->section($page, 'news.list', self::newsValues('full', 2, [$a], true), ['ca' => ['heading' => 'A', 'intro' => '']]);
        $two  = $this->section($page, 'news.list', self::newsValues('full', 2, [$b]), ['ca' => ['heading' => 'B', 'intro' => '']]);

        $body = $this->body('GET', 'totes');
        $this->assertStringContainsString('Notícia A5', $body);
        $this->assertStringContainsString('Notícia B5', $body);
        $this->assertStringNotContainsString('Notícia A3', $body);
        $this->assertStringContainsString('page-' . $one . '=2', $body, 'The first list pages with its own parameter.');
        $this->assertStringContainsString('page-' . $two . '=2', $body);

        $body = $this->body('GET', 'totes?page-' . $one . '=2');
        $this->assertStringContainsString('Notícia A3', $body, 'Page 2 of the first list.');
        $this->assertStringNotContainsString('Notícia A5', $body);
        $this->assertStringContainsString('Notícia B5', $body, 'The second list stays on its first page.');
        $this->assertStringContainsString('page-' . $one . '=2&page-' . $two . '=2', $body, 'Links keep the other list\'s page.');

        $body = $this->body('GET', 'totes?q-' . $one . '=A1');
        $this->assertStringContainsString('Notícia A1', $body, 'The search box of the first list.');
        $this->assertStringNotContainsString('Notícia A5', $body);
        $this->assertStringContainsString('Notícia B5', $body);
        $this->assertStringContainsString('name="q-' . $one . '"', $body);
        $this->assertStringNotContainsString('name="q-' . $two . '"', $body, 'The second list has no search box.');
    }

    // --- Events -----------------------------------------------------------------------------------

    private function event(string $title, string $visibility, string $status = 'confirmed'): int
    {
        $now      = Time::now();
        $tomorrow = Time::tomorrow()->setHour(10);
        db_connect()->table('events')->insert([
            'starts_at' => $tomorrow->toDateTimeString(), 'ends_at' => $tomorrow->addHours(1)->toDateTimeString(), 'all_day' => 0,
            'visibility' => $visibility, 'status' => $status, 'sequence' => 0, 'created_at' => $now->toDateTimeString(), 'updated_at' => $now->toDateTimeString(),
        ]);
        $id = (int) db_connect()->insertID();
        db_connect()->table('event_translations')->insert(['event_id' => $id, 'locale' => 'ca', 'title' => $title, 'description' => '']);

        return $id;
    }

    public function testEventSectionsShowPublicEventsOnly(): void
    {
        $this->event('Festa pública', 'public');
        $this->event('Claustre privat', 'private');
        $this->event('Sortida cancel·lada', 'public', 'cancelled');

        $page = $this->page('Agenda', ['layout' => 'sections']);
        $this->section($page, 'events.list', ['categories' => [], 'limit' => 4, 'days' => 30, 'columns' => '3', 'more' => null], ['ca' => ['heading' => 'Properes', 'intro' => '']], ['style' => 'cards']);
        $this->section($page, 'events.calendar', ['categories' => [], 'view' => 'listMonth', 'filter' => true], ['ca' => ['heading' => 'Calendari', 'intro' => '']]);
        $this->editor();

        $body = $this->body('GET', 'agenda');
        $this->assertStringContainsString('Festa pública', $body);
        $this->assertStringNotContainsString('Claustre privat', $body, 'Even for a signed-in user, like the public calendar.');
        $this->assertStringNotContainsString('Sortida cancel·lada', $body);
        $this->assertStringContainsString('calendar/event/', $body);
        $this->assertStringContainsString('data-component="events/calendar"', $body);
        $this->assertStringContainsString('"view":"listMonth"', $body);
        $this->assertStringContainsString('calendar/feed', $body);

        // The public calendar off: nothing on the page, a warning in the editor.
        config(\Modules\Events\Config\Calendar::class)->publicCalendar = false;
        $body = $this->body('GET', 'agenda');
        $this->assertStringNotContainsString('Festa pública', $body);
        $this->assertStringNotContainsString('data-component="events/calendar"', $body);
        $this->assertTranslated('Events.sections.calendarOff', [], $this->body('GET', 'backend/pages/' . $page . '/sections'));
    }

    public function testThePublicFeedFiltersSeveralCategories(): void
    {
        $now = Time::now()->toDateTimeString();
        db_connect()->table('event_categories')->insert(['slug' => 'esports', 'position' => 0, 'active' => 1, 'created_at' => $now, 'updated_at' => $now]);
        $sport = (int) db_connect()->insertID();
        $in    = $this->event('Partit', 'public');
        $this->event('Concert', 'public');
        db_connect()->table('events')->where('id', $in)->update(['category_id' => $sport]);

        $start = Time::today()->toDateString();
        $end   = Time::today()->addDays(7)->toDateString();
        $feed  = json_decode((string) $this->send('GET', 'calendar/feed?start=' . $start . '&end=' . $end . '&categories=' . $sport . ',abc,-3')->response()->getBody(), true);
        $this->assertSame(['Partit'], array_column($feed['events'] ?? [], 'title'));
    }
}
