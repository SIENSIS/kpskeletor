<?php

declare(strict_types=1);

namespace Modules\Events\Services;

use CodeIgniter\Database\BaseBuilder;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Shield\Entities\User;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Kpcrudy\Crud;
use Modules\Core\Content\ContentTranslations;
use Modules\Core\Security\HtmlSanitizer;

/**
 * Read queries of the calendar: the occurrences of the events a user may see in a date range
 * (recurrences expanded), one event's details, the categories, all with the texts in the user's
 * language (Catalan fallback). Visibility always comes from `EventVisibility`.
 */
final class EventRepository
{
    /** Masking context of event ids in URLs (the same as the administration's kpcrudy CRUD). */
    public const MASK = 'events';

    private readonly Recurrence $recurrence;

    public function __construct(?Recurrence $recurrence = null)
    {
        $this->recurrence = $recurrence ?? new Recurrence(maxOccurrences: (int) config(\Modules\Events\Config\Calendar::class)->maxOccurrences);
    }

    /**
     * The occurrences of the visible events that overlap `[$from, $to)`, by start.
     *
     * @param array{category?: int} $filters
     *
     * @return list<array<string, mixed>>
     */
    public function occurrences(?User $user, DateTimeInterface $from, DateTimeInterface $to, bool $publicOnly = false, array $filters = []): array
    {
        $storage = new DateTimeZone(config('App')->appTimezone ?: 'UTC');
        // One day of margin on both sides (all-day events and time zones); exact filter below.
        $lower = DateTimeImmutable::createFromInterface($from)->setTimezone($storage)->modify('-1 day');
        $upper = DateTimeImmutable::createFromInterface($to)->setTimezone($storage)->modify('+1 day');

        $builder = $this->visible($user, $publicOnly)
            ->where('events.starts_at <', $upper->format('Y-m-d H:i:s'))
            ->groupStart()
                ->groupStart()
                    ->where('events.recur_freq', null)
                    ->groupStart()
                        ->where('events.ends_at >=', $lower->format('Y-m-d H:i:s'))
                        ->orGroupStart()
                            ->where('events.ends_at', null)
                            ->where('events.starts_at >=', $lower->format('Y-m-d H:i:s'))
                        ->groupEnd()
                    ->groupEnd()
                ->groupEnd()
                ->orGroupStart()
                    ->where('events.recur_freq IS NOT NULL')
                    ->groupStart()
                        ->where('events.recur_until', null)
                        ->orWhere('events.recur_until >=', $lower->format('Y-m-d'))
                    ->groupEnd()
                ->groupEnd()
            ->groupEnd();

        if (($filters['category'] ?? 0) > 0) {
            $builder->where('events.category_id', (int) $filters['category']);
        }

        $rows = $this->withTexts($builder->get()->getResultArray());
        $list = [];

        foreach ($rows as $row) {
            foreach ($this->recurrence->between($row, $from, $to) as $occurrence) {
                $list[] = $row + ['occurrence_start' => $occurrence['start'], 'occurrence_end' => $occurrence['end']];
            }
        }

        usort($list, static fn (array $a, array $b): int => [$a['occurrence_start'], $a['id']] <=> [$b['occurrence_start'], $b['id']]);

        return $list;
    }

    /**
     * One visible event with its texts, category and audience, or null.
     *
     * @return array<string, mixed>|null
     */
    public function find(?User $user, int $id, bool $publicOnly = false): ?array
    {
        $row = $this->visible($user, $publicOnly)->where('events.id', $id)->get(1)->getRowArray();

        return is_array($row) ? $this->withTexts([$row])[0] : null;
    }

    /**
     * The id behind a masked code of a URL, or null.
     */
    public static function idFromCode(string $code): ?int
    {
        $id = Crud::unmaskId($code, self::MASK);

        return $id === null || ! ctype_digit($id) ? null : (int) $id;
    }

    public static function code(int $id): string
    {
        return Crud::maskId($id, self::MASK);
    }

    /**
     * Active categories with their name in the current language and their colour.
     *
     * @return list<array{id: int, slug: string, name: string, color: string|null}>
     */
    public function categories(bool $activeOnly = true): array
    {
        $builder = db_connect()->table('event_categories')->orderBy('position')->orderBy('id');

        if ($activeOnly) {
            $builder->where('active', 1);
        }

        $rows  = $builder->get()->getResultArray();
        $names = ContentTranslations::texts('event_category_translations', 'category_id', array_column($rows, 'id'), ['name']);

        return array_map(static fn (array $row): array => [
            'id'    => (int) $row['id'],
            'slug'  => (string) $row['slug'],
            'name'  => (string) ($names[(int) $row['id']]['name'] ?? $row['slug']),
            'color' => self::color($row['color'] ?? null),
        ], $rows);
    }

    /**
     * @return array{id: int, slug: string, name: string, color: string|null}|null
     */
    public function categoryBySlug(string $slug): ?array
    {
        foreach ($this->categories() as $category) {
            if ($category['slug'] === $slug) {
                return $category;
            }
        }

        return null;
    }

    /**
     * Converts an occurrence to the JSON of the calendar library (FullCalendar's event object).
     *
     * @param array<string, mixed> $row an item of `occurrences()`
     *
     * @return array<string, mixed>
     */
    public static function toFeed(array $row, string $detailsBase): array
    {
        $start  = $row['occurrence_start'];
        $end    = $row['occurrence_end'];
        $allDay = (bool) $row['all_day'];
        \assert($start instanceof DateTimeImmutable && $end instanceof DateTimeImmutable);

        $item = [
            'id'      => self::code((int) $row['id']) . '-' . $start->format('Ymd\THi'),
            'title'   => (string) $row['title'],
            'start'   => $allDay ? $start->format('Y-m-d') : $start->format('Y-m-d\TH:i:s'),
            'end'     => $allDay ? $end->format('Y-m-d') : $end->format('Y-m-d\TH:i:s'),
            'allDay'  => $allDay,
            'url'     => $detailsBase . '/' . self::code((int) $row['id']) . '?date=' . $start->format('Y-m-d'),
            'classNames' => $row['status'] === 'cancelled' ? ['event-cancelled'] : [],
            'extendedProps' => [
                'location'   => (string) ($row['location'] ?? ''),
                'category'   => $row['category_name'],
                'status'     => (string) $row['status'],
                'visibility' => (string) $row['visibility'],
                'recurring'  => $row['recur_freq'] !== null,
            ],
        ];

        if ($row['display_color'] !== null) {
            $item['color'] = $row['display_color'];
        }

        return $item;
    }

    /**
     * A safe `#rrggbb` colour, or null.
     */
    public static function color(mixed $value): ?string
    {
        return is_string($value) && preg_match('/^#[0-9a-fA-F]{6}$/', $value) === 1 ? strtolower($value) : null;
    }

    /**
     * @return BaseBuilder<BaseConnection<object|resource, object|resource>>
     */
    private function visible(?User $user, bool $publicOnly): BaseBuilder
    {
        return EventVisibility::apply(db_connect()->table('events')->select('events.*'), $user, 'events', $publicOnly);
    }

    /**
     * Adds `title`, `description` (sanitised), `category_name` and `display_color`.
     *
     * @param list<array<string, mixed>> $rows
     *
     * @return list<array<string, mixed>>
     */
    private function withTexts(array $rows): array
    {
        if ($rows === []) {
            return [];
        }

        $texts      = ContentTranslations::texts('event_translations', 'event_id', array_column($rows, 'id'), ['title', 'description']);
        $categories = [];

        foreach ($this->categories(false) as $category) {
            $categories[$category['id']] = $category;
        }

        foreach ($rows as $i => $row) {
            $category = $categories[(int) ($row['category_id'] ?? 0)] ?? null;

            $rows[$i]['title']         = (string) ($texts[(int) $row['id']]['title'] ?? '');
            $rows[$i]['description']   = HtmlSanitizer::clean((string) ($texts[(int) $row['id']]['description'] ?? ''));
            $rows[$i]['category_name'] = $category['name'] ?? null;
            $rows[$i]['display_color'] = self::color($row['color'] ?? null) ?? ($category['color'] ?? null);
        }

        return $rows;
    }
}
