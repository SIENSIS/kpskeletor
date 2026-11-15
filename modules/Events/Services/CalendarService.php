<?php

declare(strict_types=1);

namespace Modules\Events\Services;

use CodeIgniter\Events\Events;
use CodeIgniter\I18n\Time;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use Modules\Core\Calendar\CalendarEvent;
use Modules\Core\Calendar\CalendarPublisher;
use Modules\Core\Calendar\CalendarSource;
use Modules\Core\Modules\CacheInvalidator;
use Modules\Core\Security\HtmlSanitizer;

/**
 * The calendar API of the Events module (`service('calendar')`, docs/calendar-api.md, "Publish"):
 * other modules store their events in `events` with `source_module` + `source_key`.
 *
 * - `publish()` is idempotent: the same data changes nothing (no new `SEQUENCE`), different data
 *   updates the event and bumps `sequence`, an unpublished event comes back;
 * - `unpublish()` soft-deletes it (the iCal feeds publish it as cancelled for a grace period);
 * - published events are locked in the administration ("Managed by …");
 * - `purgeModule()` deletes every event of a module (uninstall), `resync()` repairs drift from the
 *   module's `CalendarSource`s.
 *
 * Every change fires a CodeIgniter event: `calendar.created`, `calendar.updated` or
 * `calendar.deleted` with `['id' => …, 'module' => …, 'key' => …]`.
 */
final class CalendarService implements CalendarPublisher
{
    public const EVENT_CREATED = 'calendar.created';
    public const EVENT_UPDATED = 'calendar.updated';
    public const EVENT_DELETED = 'calendar.deleted';

    /** Cache scope of the calendar's feeds and providers. */
    public const CACHE_SCOPE = 'calendar';

    public function publish(string $module, string $key, CalendarEvent $event): bool
    {
        self::checkSource($module, $key);

        if ($event->calendar !== null && ! str_starts_with($event->calendar, strtolower($module) . '.')) {
            throw new InvalidArgumentException("The module {$module} cannot publish to the calendar {$event->calendar}.");
        }

        $db       = db_connect();
        $values   = $this->values($event);
        $titles   = array_map(static fn (string $title): string => mb_substr(trim($title), 0, 190), $event->titles());
        $texts    = array_map(static fn (string $text): string => HtmlSanitizer::clean($text), $event->descriptions());
        $audience = $event->visibility === 'restricted' ? $this->audience($event) : ['groups' => [], 'users' => []];
        $existing = $db->table('events')->where('source_module', $module)->where('source_key', $key)->get(1)->getRowArray();
        $now      = Time::now()->toDateTimeString();

        if (is_array($existing) && $existing['deleted_at'] === null && $this->same($existing, $values, $titles, $texts, $audience)) {
            return false;
        }

        $db->transStart();

        if (is_array($existing)) {
            $id = (int) $existing['id'];
            $db->table('events')->where('id', $id)->update($values + [
                'sequence'   => (int) $existing['sequence'] + 1,
                'deleted_at' => null,
                'updated_at' => $now,
            ]);
            $db->table('event_translations')->where('event_id', $id)->delete();
            $db->table('event_audience')->where('event_id', $id)->delete();
        } else {
            $db->table('events')->insert($values + [
                'source_module' => $module,
                'source_key'    => $key,
                'sequence'      => 0,
                'created_at'    => $now,
                'updated_at'    => $now,
            ]);
            $id = (int) $db->insertID();
        }

        foreach ($titles as $locale => $title) {
            $db->table('event_translations')->insert(['event_id' => $id, 'locale' => $locale, 'title' => $title, 'description' => $texts[$locale] ?? null]);
        }

        foreach ($audience['groups'] as $group) {
            $db->table('event_audience')->insert(['event_id' => $id, 'group_id' => $group, 'user_id' => null]);
        }

        foreach ($audience['users'] as $user) {
            $db->table('event_audience')->insert(['event_id' => $id, 'group_id' => null, 'user_id' => $user]);
        }

        $db->transComplete();

        self::changed($existing === null ? self::EVENT_CREATED : self::EVENT_UPDATED, $id, $module, $key);

        return true;
    }

    public function unpublish(string $module, string $key): bool
    {
        self::checkSource($module, $key);

        $row = db_connect()->table('events')->where('source_module', $module)->where('source_key', $key)->where('deleted_at', null)->get(1)->getRowArray();

        if (! is_array($row)) {
            return false;
        }

        $now = Time::now()->toDateTimeString();
        db_connect()->table('events')->where('id', (int) $row['id'])->update([
            'deleted_at' => $now,
            'updated_at' => $now,
            'sequence'   => (int) $row['sequence'] + 1,
        ]);

        self::changed(self::EVENT_DELETED, (int) $row['id'], $module, $key);

        return true;
    }

    /**
     * Deletes every event a module published, for good (the module is uninstalled). Returns how many.
     */
    public function purgeModule(string $module): int
    {
        $db  = db_connect();
        $ids = array_map('intval', array_column($db->table('events')->select('id')->where('source_module', $module)->get()->getResultArray(), 'id'));

        if ($ids === []) {
            return 0;
        }

        $db->transStart();

        foreach (['event_reminders', 'event_audience', 'event_translations'] as $table) {
            $db->table($table)->whereIn('event_id', $ids)->delete();
        }

        $db->table('events')->whereIn('id', $ids)->delete();
        $db->transComplete();

        foreach ($ids as $id) {
            Events::trigger(self::EVENT_DELETED, ['id' => $id, 'module' => $module, 'key' => null]);
        }

        self::invalidate();

        return count($ids);
    }

    /**
     * Asks the module's calendar sources for all their events again: publishes them (unchanged
     * ones stay untouched) and unpublishes the published events they no longer return.
     *
     * @return array{published: int, unchanged: int, unpublished: int, calendars: int}
     */
    public function resync(string $module): array
    {
        $result = ['published' => 0, 'unchanged' => 0, 'unpublished' => 0, 'calendars' => 0];

        foreach ((new Calendars())->all() as $calendar) {
            if ($calendar['module'] !== $module || $calendar['source'] === null || ! is_a($calendar['source'], CalendarSource::class, true)) {
                continue;
            }

            $result['calendars']++;
            $source = new ($calendar['source'])();
            $keys   = [];

            foreach ($source->all() as $key => $event) {
                $key        = (string) $key;
                $keys[$key] = true;
                $event      = $event->calendar === null ? self::withCalendar($event, $calendar['id']) : $event;
                $this->publish($module, $key, $event) ? $result['published']++ : $result['unchanged']++;
            }

            $stale = db_connect()->table('events')->select('source_key')
                ->where('source_module', $module)->where('calendar', $calendar['id'])->where('deleted_at', null)
                ->get()->getResultArray();

            foreach ($stale as $row) {
                if (! isset($keys[(string) $row['source_key']]) && $this->unpublish($module, (string) $row['source_key'])) {
                    $result['unpublished']++;
                }
            }
        }

        return $result;
    }

    /**
     * Drops the cached feeds and provider results.
     */
    public static function invalidate(): void
    {
        (new CacheInvalidator())->invalidate([self::CACHE_SCOPE]);
    }

    /**
     * Fires the CodeIgniter event of a change and drops the caches.
     */
    public static function changed(string $event, int $id, ?string $module = null, ?string $key = null): void
    {
        self::invalidate();
        Events::trigger($event, ['id' => $id, 'module' => $module, 'key' => $key]);
    }

    private static function checkSource(string $module, string $key): void
    {
        if (preg_match('/^[A-Z][A-Za-z0-9]*$/', $module) !== 1) {
            throw new InvalidArgumentException("Invalid module name \"{$module}\".");
        }

        if (preg_match('/^[A-Za-z0-9._:-]{1,150}$/', $key) !== 1) {
            throw new InvalidArgumentException("Invalid key \"{$key}\" (letters, digits, . _ : -; at most 150).");
        }
    }

    private static function withCalendar(CalendarEvent $event, string $calendar): CalendarEvent
    {
        return new CalendarEvent(
            title: $event->title,
            start: $event->start,
            end: $event->end,
            allDay: $event->allDay,
            location: $event->location,
            description: $event->description,
            calendar: $calendar,
            visibility: $event->visibility,
            groups: $event->groups,
            users: $event->users,
            url: $event->url,
            reminders: $event->reminders,
            status: $event->status,
            color: $event->color,
            key: $event->key,
        );
    }

    /**
     * The `events` columns of a published event (dates in the storage time zone).
     *
     * @return array<string, mixed>
     */
    private function values(CalendarEvent $event): array
    {
        $storage = new DateTimeZone(config('App')->appTimezone ?: 'UTC');
        $local   = new DateTimeZone(function_exists('site_timezone') ? site_timezone() : 'UTC');

        if ($event->allDay) {
            // All-day events are whole local days: the dates of start and (inclusive) end.
            $start = new DateTimeImmutable($event->start->format('Y-m-d') . ' 00:00:00', $local);
            $end   = $event->end === null ? null : new DateTimeImmutable($event->end->format('Y-m-d') . ' 00:00:00', $local);
        } else {
            $start = $event->start;
            $end   = $event->end;
        }

        return [
            'starts_at'       => $start->setTimezone($storage)->format('Y-m-d H:i:s'),
            'ends_at'         => $end?->setTimezone($storage)->format('Y-m-d H:i:s'),
            'all_day'         => $event->allDay ? 1 : 0,
            'location'        => $event->location === null || trim($event->location) === '' ? null : mb_substr(trim($event->location), 0, 190),
            'color'           => $event->color === null ? null : strtolower($event->color),
            'url'             => null,
            'source_url'      => $event->url === null ? null : mb_substr($event->url, 0, 500),
            'visibility'      => $event->visibility,
            'status'          => $event->status,
            'calendar'        => $event->calendar,
            'category_id'     => null,
            'allow_reminders' => $event->reminders ? 1 : 0,
            'recur_freq'      => null,
            'recur_exceptions' => null,
        ];
    }

    /**
     * Group ids (by name) and existing user ids of a restricted event's audience.
     *
     * @return array{groups: list<int>, users: list<int>}
     */
    private function audience(CalendarEvent $event): array
    {
        $groups = [];

        if ($event->groups !== []) {
            $rows  = db_connect()->table('groups')->select('id, name')->whereIn('name', $event->groups)->get()->getResultArray();
            $found = array_column($rows, 'id', 'name');

            foreach ($event->groups as $name) {
                if (! isset($found[$name])) {
                    throw new InvalidArgumentException("Unknown group \"{$name}\" in the audience of a calendar event.");
                }
            }

            $groups = array_values(array_unique(array_map('intval', $found)));
            sort($groups);
        }

        $users = [];
        $ids   = array_values(array_unique(array_map('intval', $event->users)));

        if ($ids !== []) {
            $users = array_map('intval', array_column(db_connect()->table('users')->select('id')->whereIn('id', $ids)->get()->getResultArray(), 'id'));
            sort($users);
        }

        return ['groups' => $groups, 'users' => $users];
    }

    /**
     * Whether a stored event already has these values, texts and audience.
     *
     * @param array<string, mixed>                          $row
     * @param array<string, mixed>                          $values
     * @param array<string, string>                         $titles
     * @param array<string, string>                         $texts
     * @param array{groups: list<int>, users: list<int>}    $audience
     */
    private function same(array $row, array $values, array $titles, array $texts, array $audience): bool
    {
        foreach ($values as $column => $value) {
            if (($row[$column] ?? null) === null ? $value !== null : (string) $row[$column] !== (string) $value) {
                return false;
            }
        }

        $db       = db_connect();
        $stored   = $db->table('event_translations')->where('event_id', (int) $row['id'])->get()->getResultArray();
        $current  = [];

        foreach ($stored as $translation) {
            $current[(string) $translation['locale']] = [(string) $translation['title'], (string) ($translation['description'] ?? '')];
        }

        $wanted = [];

        foreach ($titles as $locale => $title) {
            $wanted[$locale] = [$title, $texts[$locale] ?? ''];
        }

        ksort($current);
        ksort($wanted);

        if ($current !== $wanted) {
            return false;
        }

        $rows   = $db->table('event_audience')->where('event_id', (int) $row['id'])->get()->getResultArray();
        $groups = array_values(array_filter(array_map(static fn (array $r): int => (int) $r['group_id'], $rows)));
        $users  = array_values(array_filter(array_map(static fn (array $r): int => (int) $r['user_id'], $rows)));
        sort($groups);
        sort($users);

        return $groups === $audience['groups'] && $users === $audience['users'];
    }
}
