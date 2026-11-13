<?php

declare(strict_types=1);

namespace Modules\Events\Widgets;

use CodeIgniter\Shield\Entities\User;
use DateTimeImmutable;
use DateTimeZone;
use Modules\Core\Widgets\Widget;
use Modules\Events\Services\EventDetails;
use Modules\Events\Services\EventRepository;

/**
 * Widget "Upcoming events": the next occurrences the user can see (`Config\Calendar::$upcomingDays`
 * ahead), with a link to the calendar. Needs `events-calendar.view`.
 */
final class UpcomingEvents extends Widget
{
    public function id(): string
    {
        return 'events.upcoming';
    }

    public function title(): string
    {
        return lang('Events.widgets.upcoming.title');
    }

    public function icon(): string
    {
        return 'bi bi-calendar-event';
    }

    public function permission(): string
    {
        return 'events-calendar.view';
    }

    public function settings(): array
    {
        return [
            'limit' => [
                'type'    => 'int',
                'default' => 5,
                'min'     => 1,
                'max'     => 20,
                'label'   => 'Events.widgets.limit',
            ],
        ];
    }

    public function render(array $options): string
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            return '';
        }

        $now  = new DateTimeImmutable('now', new DateTimeZone(site_timezone()));
        $rows = (new EventRepository())->occurrences($user, $now, $now->modify('+' . max(1, (int) config(\Modules\Events\Config\Calendar::class)->upcomingDays) . ' days'));
        $rows = array_values(array_filter($rows, static fn (array $row): bool => $row['occurrence_end'] > $now && $row['status'] !== 'cancelled'));
        $rows = array_slice($rows, 0, (int) $options['limit']);

        if ($rows === []) {
            return $this->empty(lang('Events.widgets.upcoming.empty'), 'bi bi-calendar-event');
        }

        $items = array_map(static fn (array $row): array => [
            'title'    => (string) $row['title'],
            'when'     => EventDetails::when($row['occurrence_start'], $row['occurrence_end'], (bool) $row['all_day']),
            'location' => (string) ($row['location'] ?? ''),
            'url'      => site_url('backend/calendar/event/' . EventRepository::code((int) $row['id'])) . '?date=' . $row['occurrence_start']->format('Y-m-d'),
        ], $rows);

        return $this->view('Modules\Events\Views\widgets\upcoming', ['items' => $items, 'url' => site_url('backend/calendar')]);
    }
}
