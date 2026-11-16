<?php

declare(strict_types=1);

namespace Modules\Events\Services;

use CodeIgniter\Database\BaseBuilder;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\Shield\Entities\User;
use DateTimeImmutable;
use Kpapy\Http\ApiException;
use Kpapy\Resource;
use Modules\Core\Content\ContentTranslations;
use Modules\Core\Security\HtmlSanitizer;

/**
 * The kpapy resources of the calendar (D-26-01; docs/events.md, "API"): `api/v1/events` (the
 * token's user, `events-api.*`) and `api/v1/public/events` (no token, public events only). Both
 * use the one visibility rule (`EventVisibility`): the `where(Closure)` limits list, details,
 * counts, search and filters, and the events an `EventAccessPolicy` refuses are left out too.
 *
 * - `GET …/events[/{id}]`: the stored events (recurrence as a rule, not expanded);
 * - `GET …/events/occurrences?start=YYYY-MM-DD&end=YYYY-MM-DD[&calendars=a,b][&category=<slug>]`:
 *   the occurrences in a range (recurrences expanded; the user's endpoint adds the events of the
 *   calendar providers), the same data as the calendar view;
 * - `GET api/v1/events/calendars`: the calendars the user may see (with `hidden`, the user's own
 *   choice in the calendar view; the API ignores it unless `?calendars=` is sent).
 *
 * Ids are the back office's masked codes (context `events`).
 */
final class EventsApi
{
    /**
     * @param string $basePath the resource's route (`api/v1/events`, `api/v1/public/events`)
     */
    public static function resource(?User $user, bool $publicOnly, string $basePath): Resource
    {
        $repository = new EventRepository();
        $categories = [];

        foreach ($repository->categories(false) as $category) {
            $categories[$category['id']] = $category;
        }

        $resource = (new Resource())
            ->setBasePath($basePath)
            ->setTable('events')
            // The one visibility rule (the deleted filter included), minus what access policies refuse.
            ->where(static function (BaseBuilder $builder) use ($user, $publicOnly): BaseBuilder {
                $viewer  = $publicOnly ? null : $user;
                $refused = self::refusedByPolicy($viewer);
                EventVisibility::apply($builder, $viewer, 'events', $publicOnly);

                return $refused === [] ? $builder : $builder->whereNotIn('events.id', $refused);
            })
            ->setTranslations('event_translations', 'event_id', 'locale', ['title', 'description'], ContentTranslations::LOCALES, ['required' => ['ca']])
            ->fields(['id', 'title', 'description', 'starts_at', 'ends_at', 'all_day', 'location', 'url', 'category', 'calendar', 'color', 'visibility', 'status', 'organiser', 'recurrence', 'updated_at'])
            ->listFields(['id', 'title', 'starts_at', 'ends_at', 'all_day', 'location', 'category', 'calendar', 'color', 'visibility', 'status', 'recurrence'])
            ->fieldType('all_day', 'boolean')
            ->fieldType('starts_at', 'datetime')
            ->fieldType('ends_at', 'datetime')
            ->callbackField('category', static fn ($value, array $row): ?string => $categories[(int) ($row['category_id'] ?? 0)]['slug'] ?? null)
            ->callbackField('calendar', static fn ($value, array $row): string => Calendars::of($row))
            ->callbackField('color', static fn ($value, array $row): ?string => EventRepository::color($row['color'] ?? null) ?? ($categories[(int) ($row['category_id'] ?? 0)]['color'] ?? null))
            ->callbackField('recurrence', static fn ($value, array $row): ?array => self::recurrence($row))
            ->transform(static function (array $item): array {
                if (is_string($item['description'] ?? null)) {
                    $item['description'] = HtmlSanitizer::clean($item['description']);
                } elseif (is_array($item['description'] ?? null)) {
                    $item['description'] = array_map(static fn ($text): ?string => is_string($text) ? HtmlSanitizer::clean($text) : null, $item['description']);
                }

                return $item;
            })
            ->filters(['visibility', 'status', 'all_day'])
            ->searchFields(['title', 'location'])
            ->defaultOrder('starts_at', 'desc')
            ->readOnly()
            ->unsetTrash()
            ->addOperation('occurrences', static fn (): array => self::occurrences($user, $publicOnly), [
                'row' => false, 'method' => 'get', 'permission' => 'list', 'description' => lang('Events.api.occurrences'),
            ]);

        if ($publicOnly) {
            return $resource->setResource('events-public')
                ->setPermissionResolver(null)
                ->setPermission('list', true)
                ->setPermission('show', true)
                ->describe(lang('Events.api.publicSummary'), lang('Events.api.publicDescription'));
        }

        return $resource->setResource('events-api')
            ->addOperation('calendars', static fn (): array => $user === null ? [] : self::calendars($user), [
                'row' => false, 'method' => 'get', 'permission' => 'list', 'description' => lang('Events.api.calendars'),
            ])
            ->describe(lang('Events.api.summary'), lang('Events.api.description'));
    }

    /**
     * Ids of the events of policy calendars that the policy refuses to this user (guests: all).
     *
     * @return list<int>
     */
    public static function refusedByPolicy(?User $user): array
    {
        $service  = new Calendars();
        $policies = array_keys(array_filter($service->all(), static fn (array $calendar): bool => $calendar['policy'] !== null));

        if ($policies === []) {
            return [];
        }

        $rows = db_connect()->table('events')->select('id, calendar, source_key')->whereIn('calendar', $policies)->where('deleted_at', null)->get()->getResultArray();

        return array_values(array_map(
            static fn (array $row): int => (int) $row['id'],
            array_filter($rows, static fn (array $row): bool => ! EventVisibility::allowedByPolicy($row, $user, $service)),
        ));
    }

    /**
     * The occurrences of the request's range.
     *
     * @return list<array<string, mixed>>
     */
    public static function occurrences(?User $user, bool $publicOnly): array
    {
        $request = service('request');
        $range   = $request instanceof IncomingRequest ? CalendarComponent::range($request) : null;

        if ($range === null) {
            throw ApiException::validation(['start' => [lang('Events.errors.range')]]);
        }

        $repository = new EventRepository();
        $filters    = [];
        $slug       = trim((string) $request->getGet('category'));

        if ($slug !== '') {
            $category = $repository->categoryBySlug($slug);

            if ($category === null) {
                throw ApiException::validation(['category' => [lang('Events.api.unknownCategory')]]);
            }

            $filters['category'] = $category['id'];
        }

        $visible   = (new Calendars())->visibleTo($publicOnly ? null : $user);
        $requested = Calendars::split($request->getGet('calendars'));
        $shown     = $requested === [] ? array_keys($visible) : array_values(array_intersect($requested, array_keys($visible)));

        if ($publicOnly) {
            $rows = $repository->occurrences(null, $range[0], $range[1], true, $filters + ['calendars' => $shown]);
        } else {
            $rows = $repository->occurrences($user, $range[0], $range[1], false, $filters + ['calendars' => $shown]);
        }

        $items = array_map(static fn (array $row): array => self::occurrence($row), $rows);

        // Provided events have no category; guests get none (providers compute per user).
        if (! $publicOnly && $user !== null && $slug === '') {
            foreach ((new ProvidedEvents())->occurrences($user, $range[0], $range[1], $shown) as $row) {
                $items[] = self::provided($row);
            }

            usort($items, static fn (array $a, array $b): int => [$a['start'], $a['id']] <=> [$b['start'], $b['id']]);
        }

        return $items;
    }

    /**
     * The calendars the user may see.
     *
     * @return list<array{id: string, module: string, label: string, color: string|null, hidden: bool}>
     */
    public static function calendars(User $user): array
    {
        $service = new Calendars();
        $hidden  = $service->hidden($user);
        $list    = [];

        foreach ($service->visibleTo($user) as $id => $calendar) {
            $list[] = ['id' => $id, 'module' => $calendar['module'], 'label' => $calendar['label'], 'color' => $calendar['color'], 'hidden' => in_array($id, $hidden, true)];
        }

        return $list;
    }

    /**
     * @param array<string, mixed> $row an item of `EventRepository::occurrences()`
     *
     * @return array<string, mixed>
     */
    public static function occurrence(array $row): array
    {
        $start = $row['occurrence_start'];
        $end   = $row['occurrence_end'];
        \assert($start instanceof DateTimeImmutable && $end instanceof DateTimeImmutable);
        $allDay = (bool) $row['all_day'];

        return [
            'id'         => EventRepository::code((int) $row['id']) . '-' . $start->format('Ymd\THi'),
            'event'      => EventRepository::code((int) $row['id']),
            'provided'   => false,
            'title'      => (string) $row['title'],
            'start'      => $allDay ? $start->format('Y-m-d') : $start->format(DATE_ATOM),
            'end'        => $allDay ? $end->format('Y-m-d') : $end->format(DATE_ATOM),
            'all_day'    => $allDay,
            'location'   => (string) ($row['location'] ?? ''),
            'calendar'   => Calendars::of($row),
            'category'   => $row['category_name'] ?? null,
            'color'      => $row['display_color'] ?? null,
            'status'     => (string) $row['status'],
            'visibility' => (string) $row['visibility'],
            'recurring'  => $row['recur_freq'] !== null,
            'url'        => null,
        ];
    }

    /**
     * @param array<string, mixed> $row an item of `ProvidedEvents::occurrences()`
     *
     * @return array<string, mixed>
     */
    public static function provided(array $row): array
    {
        $start = $row['occurrence_start'];
        $end   = $row['occurrence_end'];
        \assert($start instanceof DateTimeImmutable && $end instanceof DateTimeImmutable);
        $allDay = (bool) $row['all_day'];
        $url    = is_string($row['source_url'] ?? null) && $row['source_url'] !== '' ? $row['source_url'] : null;

        return [
            'id'         => 'p-' . $row['uid'],
            'event'      => null,
            'provided'   => true,
            'title'      => (string) $row['title'],
            'start'      => $allDay ? $start->format('Y-m-d') : $start->format(DATE_ATOM),
            'end'        => $allDay ? $end->format('Y-m-d') : $end->format(DATE_ATOM),
            'all_day'    => $allDay,
            'location'   => (string) ($row['location'] ?? ''),
            'calendar'   => (string) $row['calendar'],
            'category'   => null,
            'color'      => $row['display_color'] ?? null,
            'status'     => (string) $row['status'],
            'visibility' => (string) $row['visibility'],
            'recurring'  => false,
            'url'        => $url,
        ];
    }

    /**
     * The recurrence rule of an event row, or null.
     *
     * @param array<string, mixed> $row
     *
     * @return array{freq: string, interval: int, days: list<string>, until: string|null, count: int|null, exceptions: list<string>}|null
     */
    public static function recurrence(array $row): ?array
    {
        if (! is_string($row['recur_freq'] ?? null) || $row['recur_freq'] === '') {
            return null;
        }

        return [
            'freq'       => $row['recur_freq'],
            'interval'   => max(1, (int) ($row['recur_interval'] ?? 1)),
            'days'       => array_values(array_filter(array_map('trim', explode(',', strtolower((string) ($row['recur_days'] ?? '')))))),
            'until'      => is_string($row['recur_until'] ?? null) && $row['recur_until'] !== '' ? substr($row['recur_until'], 0, 10) : null,
            'count'      => is_numeric($row['recur_count'] ?? null) ? (int) $row['recur_count'] : null,
            'exceptions' => array_keys(Recurrence::exceptions((string) ($row['recur_exceptions'] ?? ''))),
        ];
    }
}
