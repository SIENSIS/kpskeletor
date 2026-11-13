<?php

declare(strict_types=1);

namespace Modules\Events\Services;

use CodeIgniter\I18n\Time;
use CodeIgniter\Shield\Entities\User;
use DateTimeImmutable;
use DateTimeZone;
use Modules\Core\Cache\CoreCache;
use Modules\Events\Config\Calendar;
use Modules\Events\Ical\IcalWriter;

/**
 * The iCal feeds of the calendar (docs/events.md, "iCal"):
 *
 * - **personal feed** of a user (`ical/user/<secret>.ics`): every event they can see now (public,
 *   private, their audiences, provided events), filtered by categories and calendars, with their
 *   reminders as `VALARM`. The secret is random, shown once and stored hashed (SHA-256) in
 *   `ical_tokens`; regenerating replaces it, revoking disables the feed;
 * - **public feed** (`ical/public/all.ics`, `ical/public/<category slug>.ics`): public events only;
 * - **one-off export** of one event (`….ics` download).
 *
 * Feeds cover `icalPastDays` before and `icalFutureDays` after today, publish events deleted in
 * the last `icalGraceDays` as cancelled, and are cached for `icalCacheSeconds` (cleared by any
 * change of the calendar). The document is deterministic, so its hash is the ETag.
 */
final class IcalFeeds
{
    private readonly Calendar $config;

    public function __construct(private readonly EventRepository $events = new EventRepository())
    {
        $this->config = config(Calendar::class);
    }

    /**
     * The user's feed row (active or revoked), or null.
     *
     * @return array<string, mixed>|null
     */
    public function token(User $user): ?array
    {
        $row = db_connect()->table('ical_tokens')->where('user_id', (int) $user->id)->get(1)->getRowArray();

        return is_array($row) ? $row : null;
    }

    /**
     * Creates or regenerates the user's feed; returns the secret (the only time it is known).
     */
    public function issue(User $user): string
    {
        $secret = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $now    = Time::now()->toDateTimeString();
        $values = ['token_hash' => self::hash($secret), 'revoked_at' => null, 'last_access_at' => null, 'last_access_ip' => null, 'updated_at' => $now];
        $db     = db_connect();

        if ($this->token($user) === null) {
            $db->table('ical_tokens')->insert($values + ['user_id' => (int) $user->id, 'created_at' => $now]);
        } else {
            $db->table('ical_tokens')->where('user_id', (int) $user->id)->update($values + ['created_at' => $now]);
        }

        return $secret;
    }

    public function revoke(User $user): bool
    {
        db_connect()->table('ical_tokens')->where('user_id', (int) $user->id)->where('revoked_at', null)
            ->update(['revoked_at' => Time::now()->toDateTimeString(), 'updated_at' => Time::now()->toDateTimeString()]);

        return db_connect()->affectedRows() > 0;
    }

    /**
     * Stores the feed's filters (only categories that exist and calendars the user can see).
     *
     * @param list<int>    $categories
     * @param list<string> $calendars
     */
    public function setFilters(User $user, array $categories, array $calendars): void
    {
        $known      = array_column($this->events->categories(false), 'id');
        $categories = array_values(array_intersect($known, array_map('intval', $categories)));
        $calendars  = array_values(array_intersect(array_keys((new Calendars())->visibleTo($user)), $calendars));

        db_connect()->table('ical_tokens')->where('user_id', (int) $user->id)->update([
            'categories' => $categories === [] ? null : implode(',', $categories),
            'calendars'  => $calendars === [] ? null : implode(',', $calendars),
            'updated_at' => Time::now()->toDateTimeString(),
        ]);
    }

    /**
     * The active feed row of a secret (a revoked or unknown secret: null).
     *
     * @return array<string, mixed>|null
     */
    public function findBySecret(string $secret): ?array
    {
        if (preg_match('/^[A-Za-z0-9_-]{43}$/', $secret) !== 1) {
            return null;
        }

        $row = db_connect()->table('ical_tokens')->where('token_hash', self::hash($secret))->where('revoked_at', null)->get(1)->getRowArray();

        return is_array($row) ? $row : null;
    }

    /**
     * Records an access to a feed; returns whether it is worth logging (first access, a new IP
     * address or the first one in a day).
     *
     * @param array<string, mixed> $row
     */
    public function touch(array $row, string $ip): bool
    {
        $now   = Time::now();
        $last  = is_string($row['last_access_at'] ?? null) ? Time::parse($row['last_access_at']) : null;
        $worth = $last === null || ($row['last_access_ip'] ?? null) !== $ip || $last->isBefore($now->subHours(24));

        db_connect()->table('ical_tokens')->where('id', (int) $row['id'])->update([
            'last_access_at' => $now->toDateTimeString(),
            'last_access_ip' => mb_substr($ip, 0, 45),
        ]);

        return $worth;
    }

    /**
     * The personal feed of a user with the filters of their feed row.
     *
     * @param array<string, mixed> $row
     *
     * @return array{body: string, modified: int}
     */
    public function personal(User $user, array $row): array
    {
        $categories = self::ids($row['categories'] ?? null);
        $calendars  = Calendars::split($row['calendars'] ?? null);
        $key        = 'user_' . (int) $user->id . '_' . md5(implode(',', $categories) . '|' . implode(',', $calendars));

        return $this->cached($key, function () use ($user, $categories, $calendars): array {
            [$from, $to] = $this->range();
            $filters     = ['categories' => $categories];

            if ($calendars !== []) {
                $filters['calendars'] = $calendars;
            }

            $rows = $this->unique($this->events->occurrences($user, $from, $to, false, $filters));
            $rows = [...$rows, ...$this->filtered($this->events->deletedSince($user, $this->graceStart()), $categories, $calendars)];
            $base = site_url('backend/calendar/event');
            $alarms = $this->alarms($user, array_map(static fn (array $r): int => (int) $r['id'], $rows));

            foreach ($rows as $i => $event) {
                $rows[$i]['ical_url']    = $base . '/' . EventRepository::code((int) $event['id']);
                $rows[$i]['ical_alarms'] = (bool) ($event['allow_reminders'] ?? true) && isset($alarms[(int) $event['id']]) ? [$alarms[(int) $event['id']]] : [];
            }

            foreach ((new ProvidedEvents())->occurrences($user, $from, $to, $calendars === [] ? null : $calendars) as $provided) {
                $provided['ical_url'] = $provided['source_url'] ?? null;
                $rows[]               = $provided;
            }

            return $this->document(lang('Events.ical.personalName', [self::siteName()]), $rows, $from, $to);
        });
    }

    /**
     * The public feed: every public event, or those of one category.
     *
     * @param array{id: int, slug: string, name: string, color: string|null}|null $category
     *
     * @return array{body: string, modified: int}
     */
    public function public(?array $category): array
    {
        $key = 'public_' . ($category === null ? 'all' : (int) $category['id']);

        return $this->cached($key, function () use ($category): array {
            [$from, $to] = $this->range();
            $filters     = $category === null ? [] : ['categories' => [$category['id']], 'calendars' => [Calendars::OWN]];
            $rows        = $this->unique($this->events->occurrences(null, $from, $to, true, $filters));
            $deleted     = $this->events->deletedSince(null, $this->graceStart(), true);
            $rows        = [...$rows, ...($category === null ? $deleted : $this->filtered($deleted, [$category['id']], [Calendars::OWN]))];
            $base        = site_url('calendar/event');

            foreach ($rows as $i => $event) {
                $rows[$i]['ical_url'] = $base . '/' . EventRepository::code((int) $event['id']);
            }

            $name = $category === null ? lang('Events.ical.publicName', [self::siteName()]) : lang('Events.ical.categoryName', [self::siteName(), $category['name']]);

            return $this->document($name, $rows, $from, $to);
        });
    }

    /**
     * One event as a document to import (a static copy).
     *
     * @param array<string, mixed> $event a row of `EventRepository::find()`
     */
    public function single(array $event, string $url): string
    {
        $event['ical_url'] = $url;
        [$from, $to]       = $this->range();

        return $this->writer()->calendar((string) $event['title'], [$event], $from, $to);
    }

    /**
     * The URL of a personal feed.
     */
    public static function url(string $secret): string
    {
        return site_url('ical/user/' . $secret . '.ics');
    }

    /**
     * The same URL with the `webcal://` scheme (opens the subscription in calendar apps).
     */
    public static function webcal(string $url): string
    {
        return (string) preg_replace('#^https?://#', 'webcal://', $url);
    }

    /**
     * Category ids of a comma-separated list.
     *
     * @return list<int>
     */
    public static function ids(mixed $value): array
    {
        if (! is_string($value)) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map('intval', explode(',', $value)), static fn (int $id): bool => $id > 0)));
    }

    public static function hash(string $secret): string
    {
        return hash('sha256', $secret);
    }

    /**
     * @return array{0: DateTimeImmutable, 1: DateTimeImmutable}
     */
    private function range(): array
    {
        $today = new DateTimeImmutable('today', new DateTimeZone(function_exists('site_timezone') ? site_timezone() : 'UTC'));

        return [$today->modify('-' . max(1, $this->config->icalPastDays) . ' days'), $today->modify('+' . max(1, $this->config->icalFutureDays) . ' days')];
    }

    private function graceStart(): DateTimeImmutable
    {
        return new DateTimeImmutable('-' . max(0, $this->config->icalGraceDays) . ' days');
    }

    /**
     * @param list<array<string, mixed>> $rows occurrences
     *
     * @return list<array<string, mixed>> one row per event
     */
    private function unique(array $rows): array
    {
        $events = [];

        foreach ($rows as $row) {
            $events[(int) $row['id']] ??= $row;
        }

        return array_values($events);
    }

    /**
     * Rows kept by the category and calendar filters of a feed.
     *
     * @param list<array<string, mixed>> $rows
     * @param list<int>                  $categories
     * @param list<string>               $calendars
     *
     * @return list<array<string, mixed>>
     */
    private function filtered(array $rows, array $categories, array $calendars): array
    {
        return array_values(array_filter($rows, static fn (array $row): bool => ($calendars === [] || in_array(Calendars::of($row), $calendars, true))
            && ($categories === [] || ($row['calendar'] ?? null) !== null || in_array((int) ($row['category_id'] ?? 0), $categories, true))));
    }

    /**
     * The user's reminders (minutes) of these events.
     *
     * @param list<int> $ids
     *
     * @return array<int, int>
     */
    private function alarms(User $user, array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $rows = db_connect()->table('event_reminders')->select('event_id, minutes_before')->where('user_id', (int) $user->id)->whereIn('event_id', $ids)->get()->getResultArray();

        return array_map('intval', array_column($rows, 'minutes_before', 'event_id'));
    }

    /**
     * @param list<array<string, mixed>> $rows
     *
     * @return array{body: string, modified: int}
     */
    private function document(string $name, array $rows, DateTimeImmutable $from, DateTimeImmutable $to): array
    {
        $storage  = new DateTimeZone(config('App')->appTimezone ?: 'UTC');
        $modified = 0;

        foreach ($rows as $row) {
            if (is_string($row['updated_at'] ?? null)) {
                $modified = max($modified, (new DateTimeImmutable($row['updated_at'], $storage))->getTimestamp());
            }
        }

        return ['body' => $this->writer()->calendar($name, $rows, $from, $to), 'modified' => $modified > 0 ? $modified : time()];
    }

    /**
     * @param \Closure(): array{body: string, modified: int} $compute
     *
     * @return array{body: string, modified: int}
     */
    private function cached(string $key, \Closure $compute): array
    {
        $seconds = $this->config->icalCacheSeconds;

        return $seconds > 0 ? (new CoreCache())->remember(CalendarService::CACHE_SCOPE, 'ical_' . $key, $compute, $seconds) : $compute();
    }

    private function writer(): IcalWriter
    {
        $host = parse_url(base_url(), PHP_URL_HOST);

        return new IcalWriter(is_string($host) && $host !== '' ? $host : 'localhost', null, $this->config->icalRefresh);
    }

    private static function siteName(): string
    {
        $name = setting('Core.siteName');

        return is_string($name) && $name !== '' ? $name : 'kpskeletor';
    }
}
