<?php

declare(strict_types=1);

namespace Modules\Events\Tests;

use Modules\Events\Services\EventVisibility;

/**
 * Restricted audiences (acceptance: "an event only for John appears only in John's calendar"):
 * the calendar feeds, the details pages, the public site and the visibility rule itself, for
 * ordinary users, members of the audience's groups, administrators and superadmins.
 *
 * @internal
 */
final class AudienceTest extends EventsTestCase
{
    public function testAnEventOnlyForJohnAppearsOnlyInJohnsCalendar(): void
    {
        $john = $this->createUser('john@example.com', 'user');
        $this->createUser('ana@example.com', 'user');
        $this->createUser('boss@example.com', 'admin');
        $this->createUser('root@example.com', 'superadmin');
        $only = $this->event('Només per al John', ['visibility' => 'restricted'], [], [(int) $john->id]);
        $this->event('Per a tothom', ['visibility' => 'private']);

        foreach (['john@example.com' => true, 'ana@example.com' => false, 'boss@example.com' => false, 'root@example.com' => false] as $email => $sees) {
            $this->login($email);
            $titles = $this->feedTitles();

            $this->assertContains('Per a tothom', $titles, $email);
            $this->assertSame($sees, in_array('Només per al John', $titles, true), $email . ': calendar feed.');

            if ($sees) {
                $this->assertStringContainsString('Només per al John', $this->body('GET', 'backend/calendar/event/' . $this->code($only)));
            } else {
                $this->assertNotFound('GET', 'backend/calendar/event/' . $this->code($only));
            }

            $this->send('POST', 'logout');
        }
    }

    public function testAGroupAndUserAudienceIsVisibleOnlyToThem(): void
    {
        $john = $this->createUser('john@example.com', 'user');
        $this->createUser('edi@example.com', 'editor');
        $this->createUser('ana@example.com', 'user');
        $this->createUser('boss@example.com', 'admin');
        $this->event('Editors i John', ['visibility' => 'restricted'], ['editor'], [(int) $john->id]);

        foreach (['john@example.com' => true, 'edi@example.com' => true, 'ana@example.com' => false, 'boss@example.com' => false] as $email => $sees) {
            $this->login($email);
            $this->assertSame($sees, in_array('Editors i John', $this->feedTitles(), true), $email);
            $this->send('POST', 'logout');
        }
    }

    public function testTheRuleItself(): void
    {
        $john   = $this->createUser('john@example.com', 'user');
        $editor = $this->createUser('edi@example.com', 'editor');
        $root   = $this->createUser('root@example.com', 'superadmin');
        $public = $this->event('Pública', ['visibility' => 'public']);
        $priv   = $this->event('Privada', ['visibility' => 'private']);
        $group  = $this->event('Editors', ['visibility' => 'restricted'], ['editor']);
        $empty  = $this->event('Ningú', ['visibility' => 'restricted']);
        $gone   = $this->event('Esborrada', ['visibility' => 'public', 'deleted_at' => '2026-01-01 10:00:00']);

        $this->assertTrue(EventVisibility::canSee($public, null));
        $this->assertFalse(EventVisibility::canSee($priv, null), 'Guests only see public events.');
        $this->assertTrue(EventVisibility::canSee($priv, $john));
        $this->assertFalse(EventVisibility::canSee($priv, $john, true), 'The public site shows public events only.');
        $this->assertFalse(EventVisibility::canSee($group, $john));
        $this->assertTrue(EventVisibility::canSee($group, $editor));
        $this->assertFalse(EventVisibility::canSee($group, $root), 'Superadmins follow the audience too.');
        $this->assertFalse(EventVisibility::canSee($empty, $root), 'A restricted event without audience is seen by nobody.');
        $this->assertFalse(EventVisibility::canSee($gone, $john), 'Deleted events are never visible.');
    }

    public function testThePublicCalendarShowsPublicEventsOnlyEvenWhenSignedIn(): void
    {
        $john   = $this->createUser('john@example.com', 'user');
        $public = $this->event('Oberta al públic', ['visibility' => 'public']);
        $priv   = $this->event('Interna', ['visibility' => 'private']);
        $this->event('Personal', ['visibility' => 'restricted'], [], [(int) $john->id]);

        $this->assertSame(['Oberta al públic'], $this->feedTitles('calendar/feed'));
        $this->assertStringContainsString('Oberta al públic', $this->body('GET', 'calendar/event/' . $this->code($public)));
        $this->assertNotFound('GET', 'calendar/event/' . $this->code($priv));

        $this->login('john@example.com');
        $this->assertSame(['Oberta al públic'], $this->feedTitles('calendar/feed'));
        $this->assertNotFound('GET', 'calendar/event/' . $this->code($priv));
        $this->send('GET', 'calendar')->assertOK();
    }

    public function testTheCalendarNeedsItsPermissionAndAValidRange(): void
    {
        $this->send('GET', 'backend/calendar')->assertRedirectTo(site_url('login'));
        $this->send('GET', 'backend/calendar/feed?start=2026-01-01&end=2026-02-01')->assertRedirectTo(site_url('login'));

        $this->loginWith();
        $this->send('GET', 'backend/calendar')->assertOK();

        foreach (['', '?start=2026-01-01', '?start=2026-02-01&end=2026-01-01', '?start=2026-01-01&end=2028-01-01', '?start=x&end=y', '?start=2026-02-30&end=2026-03-10'] as $query) {
            $this->send('GET', 'backend/calendar/feed' . $query)->assertStatus(400);
        }

        $this->send('GET', 'calendar/feed?start=2026-01-01&end=2030-01-01')->assertStatus(400);
    }

    public function testTheCalendarPageMountsTheComponentWithoutInlineCode(): void
    {
        $this->loginWith();
        $html = (string) $this->send('GET', 'backend/calendar')->response()->getBody();

        $this->assertMatchesRegularExpression('/data-component="events\/calendar"/', $html);
        $this->assertStringContainsString('fullcalendar/global.js', html_entity_decode($html));
        $this->assertStringContainsString('backend/calendar/feed', html_entity_decode($html));
        $this->assertDoesNotMatchRegularExpression('/\sstyle="/', $html);
    }

    public function testFeedFiltersByCategoryAndKeepsCancelledEventsMarked(): void
    {
        db_connect()->table('event_categories')->insert(['slug' => 'curs', 'color' => '#ff0000', 'position' => 1, 'active' => 1]);
        $category = (int) db_connect()->insertID();
        db_connect()->table('event_category_translations')->insert(['category_id' => $category, 'locale' => 'ca', 'name' => 'Cursos']);
        $this->event('Amb categoria', ['category_id' => $category]);
        $this->event('Sense categoria');
        $this->event('Anul·lada', ['status' => 'cancelled']);
        $this->loginWith();

        $this->assertSame(['Amb categoria'], $this->feedTitles('backend/calendar/feed', 30, '&category=' . $category));

        $start = date('Y-m-d');
        $data  = json_decode((string) $this->send('GET', 'backend/calendar/feed?start=' . $start . '&end=' . date('Y-m-d', time() + 5 * 86400))->response()->getBody(), true);
        $this->assertIsArray($data);
        $byTitle = array_column($data['events'], null, 'title');

        $this->assertSame('#ff0000', $byTitle['Amb categoria']['color'], 'The category colour.');
        $this->assertSame(['event-cancelled'], $byTitle['Anul·lada']['classNames']);
        $this->assertStringContainsString('backend/calendar/event/', $byTitle['Sense categoria']['url']);
    }
}
