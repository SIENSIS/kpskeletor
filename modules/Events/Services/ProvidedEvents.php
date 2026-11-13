<?php

declare(strict_types=1);

namespace Modules\Events\Services;

use CodeIgniter\Shield\Entities\User;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Modules\Core\Cache\CoreCache;
use Modules\Core\Calendar\CalendarEvent;
use Modules\Core\Calendar\CalendarProvider;
use Modules\Core\Registry\DeclarationRegistry;
use Modules\Core\Security\HtmlSanitizer;
use Throwable;

/**
 * Events computed on the fly by the `CalendarProvider`s of enabled modules (pull mode,
 * docs/calendar-api.md, "Providers"): nothing is stored. Only for signed-in users (never on the
 * public site or in public feeds), only for calendars the user may see, cached per user and range
 * for `Config\Calendar::$providerCacheSeconds`.
 *
 * Rows have the shape of `EventRepository::occurrences()` plus `provided` = true and `uid`
 * (`<calendar>-<key>`); they have no details page (their `url` links back to the module).
 */
final class ProvidedEvents
{
    public function __construct(
        private readonly DeclarationRegistry $registry = new DeclarationRegistry(),
        private readonly Calendars $calendars = new Calendars(),
    ) {
    }

    /**
     * The provided occurrences overlapping `[$from, $to)`.
     *
     * @param list<string>|null $only calendar ids to keep (null: every calendar)
     *
     * @return list<array<string, mixed>>
     */
    public function occurrences(User $user, DateTimeInterface $from, DateTimeInterface $to, ?array $only = null): array
    {
        $visible = $this->calendars->visibleTo($user);
        $seconds = (int) config(\Modules\Events\Config\Calendar::class)->providerCacheSeconds;
        $rows    = [];

        foreach ($this->registry->calendarProviders() as $class) {
            if (! is_a($class, CalendarProvider::class, true)) {
                continue;
            }

            $compute = fn (): array => $this->fetch($class, $user, $from, $to);
            $key     = 'provider_' . md5($class) . '_' . (int) $user->id . '_' . $from->getTimestamp() . '_' . $to->getTimestamp();
            $list    = $seconds > 0 ? (new CoreCache())->remember(CalendarService::CACHE_SCOPE, $key, $compute, $seconds) : $compute();

            foreach ($list as $row) {
                if (isset($visible[$row['calendar']]) && ($only === null || in_array($row['calendar'], $only, true))) {
                    $row['display_color']  = $row['display_color'] ?? $visible[$row['calendar']]['color'];
                    $row['calendar_label'] = $visible[$row['calendar']]['label'];
                    $rows[]                = $row + ['occurrence_start' => self::date($row['start']), 'occurrence_end' => self::date($row['end'])];
                }
            }
        }

        usort($rows, static fn (array $a, array $b): int => [$a['start'], $a['uid']] <=> [$b['start'], $b['uid']]);

        return $rows;
    }

    /**
     * The feed item of the calendar component for a provided occurrence.
     *
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>
     */
    public static function toFeed(array $row): array
    {
        $start  = $row['occurrence_start'];
        $end    = $row['occurrence_end'];
        $allDay = (bool) $row['all_day'];
        \assert($start instanceof DateTimeImmutable && $end instanceof DateTimeImmutable);

        $item = [
            'id'         => 'p-' . $row['uid'],
            'title'      => (string) $row['title'],
            'start'      => $allDay ? $start->format('Y-m-d') : $start->format('Y-m-d\TH:i:s'),
            'end'        => $allDay ? $end->format('Y-m-d') : $end->format('Y-m-d\TH:i:s'),
            'allDay'     => $allDay,
            'classNames' => ['event-provided'],
            'extendedProps' => [
                'location' => (string) ($row['location'] ?? ''),
                'calendar' => $row['calendar_label'],
                'status'   => (string) $row['status'],
            ],
        ];

        if (is_string($row['source_url'] ?? null) && $row['source_url'] !== '') {
            $item['url'] = $row['source_url'];
        }

        if ($row['display_color'] !== null) {
            $item['color'] = $row['display_color'];
        }

        return $item;
    }

    /**
     * Calls one provider and converts its events to cacheable rows (strings, no objects).
     *
     * @param class-string<CalendarProvider> $class
     *
     * @return list<array<string, mixed>>
     */
    private function fetch(string $class, User $user, DateTimeInterface $from, DateTimeInterface $to): array
    {
        try {
            $provider = new $class();
            $default  = $provider->calendar();
            $events   = $provider->events($from, $to, $user);
            $rows     = [];

            foreach ($events as $event) {
                if ($event->key === null) {
                    continue;
                }

                $calendar = $event->calendar ?? $default;

                // A provider may only fill its own module's calendars.
                if (strtok($calendar, '.') !== strtok($default, '.')) {
                    continue;
                }

                [$start, $end] = self::bounds($event);

                if ($start >= DateTimeImmutable::createFromInterface($to) || $end <= DateTimeImmutable::createFromInterface($from)) {
                    continue;
                }

                $rows[] = [
                    'provided'      => true,
                    'uid'           => $calendar . '-' . $event->key,
                    'calendar'      => $calendar,
                    'title'         => $event->titleIn(service('request')->getLocale()),
                    'description'   => HtmlSanitizer::clean($event->descriptionIn(service('request')->getLocale())),
                    'location'      => $event->location,
                    'start'         => $start->format(DATE_ATOM),
                    'end'           => $end->format(DATE_ATOM),
                    'all_day'       => $event->allDay ? 1 : 0,
                    'status'        => $event->status,
                    'visibility'    => $event->visibility,
                    'source_url'    => $event->url,
                    'display_color' => $event->color,
                    'category_name' => null,
                ];
            }

            return $rows;
        } catch (Throwable $e) {
            // A broken provider must not break the calendar.
            log_message('error', 'Calendar provider {class} failed: {message}', ['class' => $class, 'message' => $e->getMessage()]);

            return [];
        }
    }

    /**
     * Start and exclusive end in the site's time zone (all day: whole local days).
     *
     * @return array{0: DateTimeImmutable, 1: DateTimeImmutable}
     */
    public static function bounds(CalendarEvent $event): array
    {
        $zone = new DateTimeZone(function_exists('site_timezone') ? site_timezone() : 'UTC');

        if ($event->allDay) {
            $start = new DateTimeImmutable($event->start->format('Y-m-d') . ' 00:00:00', $zone);
            $end   = ($event->end === null ? $start : new DateTimeImmutable($event->end->format('Y-m-d') . ' 00:00:00', $zone))->modify('+1 day');

            return [$start, $end];
        }

        $start = $event->start->setTimezone($zone);
        $end   = $event->end === null || $event->end <= $event->start ? $start->modify('+1 hour') : $event->end->setTimezone($zone);

        return [$start, $end];
    }

    private static function date(string $value): DateTimeImmutable
    {
        $zone = new DateTimeZone(function_exists('site_timezone') ? site_timezone() : 'UTC');

        return (new DateTimeImmutable($value))->setTimezone($zone);
    }
}
