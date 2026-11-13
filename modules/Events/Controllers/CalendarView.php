<?php

declare(strict_types=1);

namespace Modules\Events\Controllers;

use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Shield\Entities\User;
use Modules\Core\Controllers\BackendController;
use Modules\Events\Services\CalendarComponent;
use Modules\Events\Services\EventDetails;
use Modules\Events\Services\EventRepository;
use Modules\Events\Services\Reminders;

/**
 * The calendar of the back office (resource `events-calendar`, `events-calendar.view`, every
 * group): only the events the signed-in user can see (public, private and restricted to one of
 * their groups or to them), **administrators included** (`EventVisibility`).
 *
 * - `GET backend/calendar` (`?category=<id>`): month, week, day and list views;
 * - `GET backend/calendar/feed?start=&end=[&category=]`: JSON for the calendar component;
 * - `GET backend/calendar/event/{code}[?date=YYYY-MM-DD]`: an event's details (anything not
 *   visible is a 404) and the user's reminder;
 * - `POST backend/calendar/event/{code}/reminder` (`minutes`: one of
 *   `Config\Calendar::$reminderOptions`, empty to remove it).
 */
final class CalendarView extends BackendController
{
    public function index(): string
    {
        $repository = new EventRepository();

        return $this->render('Modules\Events\Views\calendar\index', [
            'title'      => lang('Events.calendarTitle'),
            'icon'       => 'bi bi-calendar3',
            'categories' => $repository->categories(),
            'selected'   => max(0, (int) $this->request->getGet('category')),
            'options'    => CalendarComponent::options(site_url('backend/calendar/feed'), 'events-category'),
            'actions'    => $this->can('events-events.list')
                ? [['label' => lang('Events.manage'), 'url' => site_url('backend/events'), 'icon' => 'bi bi-pencil-square']]
                : [],
        ]);
    }

    public function feed(): ResponseInterface
    {
        $range = CalendarComponent::range($this->request);

        if ($range === null) {
            return $this->json(['error' => lang('Events.errors.range')], 400);
        }

        $rows = (new EventRepository())->occurrences($this->currentUser(), $range[0], $range[1], false, [
            'category' => max(0, (int) $this->request->getGet('category')),
        ]);
        $base = site_url('backend/calendar/event');

        return $this->json(['events' => array_map(static fn (array $row): array => EventRepository::toFeed($row, $base), $rows)])
            ->setHeader('Cache-Control', 'private, no-store');
    }

    public function show(string $code): string
    {
        $user  = $this->currentUser();
        $event = $this->visible($code, $user);
        $date  = $this->request->getGet('date');

        $this->breadcrumb(lang('Events.calendarTitle'), site_url('backend/calendar'));

        return $this->render('Modules\Events\Views\calendar\show', [
            'title'     => (string) $event['title'],
            'icon'      => 'bi bi-calendar-event',
            'event'     => EventDetails::build($event, is_string($date) ? $date : null),
            'reminder'  => (new Reminders())->of((int) $event['id'], (int) $user->id),
            'options'   => array_map('intval', (array) config(\Modules\Events\Config\Calendar::class)->reminderOptions),
            'formUrl'   => site_url('backend/calendar/event/' . $code . '/reminder'),
            'backUrl'   => site_url('backend/calendar'),
            'editUrl'   => $this->can('events-events.edit') ? site_url('backend/events/edit/' . $code) : null,
        ]);
    }

    public function reminder(string $code): RedirectResponse
    {
        $user    = $this->currentUser();
        $event   = $this->visible($code, $user);
        $value   = (string) $this->request->getPost('minutes');
        $allowed = array_map('intval', (array) config(\Modules\Events\Config\Calendar::class)->reminderOptions);
        $minutes = $value === '' ? null : (int) $value;

        if ($minutes !== null && ! in_array($minutes, $allowed, true)) {
            return redirect()->to(site_url('backend/calendar/event/' . $code))->with('error', lang('Events.errors.reminder'));
        }

        (new Reminders())->set((int) $event['id'], (int) $user->id, $minutes);

        return redirect()->to(site_url('backend/calendar/event/' . $code))
            ->with('success', lang($minutes === null ? 'Events.reminderRemoved' : 'Events.reminderSaved'));
    }

    /**
     * The event behind a masked code if the user can see it; otherwise a 404.
     *
     * @return array<string, mixed>
     */
    private function visible(string $code, User $user): array
    {
        $id    = EventRepository::idFromCode($code);
        $event = $id === null ? null : (new EventRepository())->find($user, $id);

        if ($event === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        return $event;
    }
}
