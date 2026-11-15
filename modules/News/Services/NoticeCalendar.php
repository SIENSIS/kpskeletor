<?php

declare(strict_types=1);

namespace Modules\News\Services;

use CodeIgniter\I18n\Time;
use DateTimeImmutable;
use DateTimeZone;
use Kpcrudy\Crud;
use Modules\Core\Calendar\CalendarEvent;
use Modules\Core\Calendar\CalendarPublisher;
use Modules\Core\Calendar\CalendarSource;

/**
 * Notices in the calendar (calendar API, push; docs/news.md, "Calendar"): a **published notice
 * with an event date** is published to the `news.notices` calendar with the notice's audience —
 * `public` → public, `private` → private, `groups` → restricted to its groups (D-00-41). Any other
 * state (draft, scheduled, expired, an article, no date, in the trash, deleted) unpublishes it.
 *
 * `sync()` is called after every change of a post (form, publish/unpublish, bulk actions, the
 * ExpireNotices task); `all()` lets `php spark calendar:resync News` repair drift.
 */
final class NoticeCalendar implements CalendarSource
{
    public const MODULE   = 'News';
    public const CALENDAR = 'news.notices';

    /**
     * Publishes or unpublishes the event of one post. Returns whether the calendar changed.
     */
    public static function sync(int $id): bool
    {
        $row     = db_connect()->table('news')->where('id', $id)->get(1)->getRowArray();
        $event   = is_array($row) ? self::event($row) : null;
        $service = self::calendar();

        return $event === null ? $service->unpublish(self::MODULE, self::key($id)) : $service->publish(self::MODULE, self::key($id), $event);
    }

    /**
     * @param list<int|string> $ids
     */
    public static function syncMany(array $ids): void
    {
        foreach ($ids as $id) {
            self::sync((int) $id);
        }
    }

    public static function key(int $id): string
    {
        return 'notice-' . $id;
    }

    /**
     * @return iterable<string, CalendarEvent>
     */
    public function all(): iterable
    {
        $rows = db_connect()->table('news')->where('type', 'notice')->where('status', 'published')
            ->where('deleted_at', null)->where('event_starts_at IS NOT NULL')->get()->getResultArray();

        foreach ($rows as $row) {
            $event = self::event($row);

            if ($event !== null) {
                yield self::key((int) $row['id']) => $event;
            }
        }
    }

    /**
     * The calendar event of a post, or null when it must not be in the calendar.
     *
     * @param array<string, mixed> $row a `news` row
     */
    public static function event(array $row): ?CalendarEvent
    {
        $now = Time::now();

        if (($row['type'] ?? '') !== 'notice' || ($row['status'] ?? '') !== 'published' || ($row['deleted_at'] ?? null) !== null
            || ! is_string($row['event_starts_at'] ?? null) || $row['event_starts_at'] === ''
            || (is_string($row['published_at'] ?? null) && Time::parse($row['published_at'])->isAfter($now))
            || (is_string($row['expires_at'] ?? null) && ! Time::parse($row['expires_at'])->isAfter($now))) {
            return null;
        }

        $id      = (int) $row['id'];
        $storage = new DateTimeZone(config('App')->appTimezone ?: 'UTC');
        $start   = new DateTimeImmutable($row['event_starts_at'], $storage);
        $end     = is_string($row['event_ends_at'] ?? null) && $row['event_ends_at'] !== '' ? new DateTimeImmutable($row['event_ends_at'], $storage) : null;
        $allDay  = (bool) ($row['event_all_day'] ?? false);
        $site    = new DateTimeZone(function_exists('site_timezone') ? site_timezone() : 'UTC');

        if ($allDay) {
            // All-day dates are the local dates of the stored times.
            $start = $start->setTimezone($site);
            $end   = $end?->setTimezone($site);
        }

        if ($end !== null && $end < $start) {
            $end = null;
        }

        $texts  = db_connect()->table('news_translations')->select('locale, title, summary')->where('news_id', $id)->get()->getResultArray();
        $titles = array_filter(array_column($texts, 'title', 'locale'), static fn ($title): bool => is_string($title) && trim($title) !== '');

        if ($titles === []) {
            return null;
        }

        $visibility = match ((string) $row['visibility']) {
            'public' => 'public',
            'groups' => 'restricted',
            default  => 'private',
        };

        return new CalendarEvent(
            title: $titles,
            start: $start,
            end: $end,
            allDay: $allDay,
            description: array_filter(array_column($texts, 'summary', 'locale'), static fn ($text): bool => is_string($text) && $text !== ''),
            calendar: self::CALENDAR,
            visibility: $visibility,
            groups: $visibility === 'restricted' ? self::groups($id) : [],
            url: '/backend/news/read/' . Crud::maskId($id, 'news'),
            reminders: true,
        );
    }

    /**
     * @return list<string> names of the post's groups
     */
    private static function groups(int $id): array
    {
        $rows = db_connect()->table('news_groups')->select('groups.name')
            ->join('groups', 'groups.id = news_groups.group_id')
            ->where('news_groups.news_id', $id)
            ->orderBy('groups.name')
            ->get()->getResultArray();

        return array_map(static fn (array $row): string => (string) $row['name'], $rows);
    }

    private static function calendar(): CalendarPublisher
    {
        /** @var CalendarPublisher */
        return service('calendar');
    }
}
