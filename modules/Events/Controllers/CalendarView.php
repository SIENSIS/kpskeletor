<?php

declare(strict_types=1);

namespace Modules\Events\Controllers;

use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Shield\Entities\User;
use Modules\Core\Controllers\BackendController;
use Modules\Events\Services\CalendarComponent;
use Modules\Events\Services\Calendars;
use Modules\Events\Services\EventDetails;
use Modules\Events\Services\EventRepository;
use Modules\Events\Services\IcalExport;
use Modules\Events\Services\IcalFeeds;
use Modules\Events\Services\ProvidedEvents;
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
 *   `Config\Calendar::$reminderOptions`, empty to remove it; refused when the event does not
 *   allow reminders);
 * - `GET backend/calendar/event/{code}/export.ics`: one-off iCal export;
 * - `POST backend/calendar/calendars`: the calendars the user shows or hides.
 *
 * Events of other modules' calendars and providers are included (docs/calendar-api.md).
 */
final class CalendarView extends BackendController
{
    public function index(): string
    {
        $repository = new EventRepository();
        $service    = new Calendars();
        $user       = $this->currentUser();

        return $this->render('Modules\Events\Views\calendar\index', [
            'calendars'  => array_values($service->visibleTo($user)),
            'hidden'     => $service->hidden($user),
            'saveUrl'    => site_url('backend/calendar/calendars'),
            'title'      => lang('Events.calendarTitle'),
            'icon'       => 'bi bi-calendar3',
            'categories' => $repository->categories(),
            'selected'   => max(0, (int) $this->request->getGet('category')),
            'options'    => CalendarComponent::options(site_url('backend/calendar/feed'), 'events-category'),
            'actions'    => [
                ...($this->can('events-events.list') ? [['label' => lang('Events.manage'), 'url' => site_url('backend/events'), 'icon' => 'bi bi-pencil-square']] : []),
                ['label' => lang('Events.subscription.menu'), 'url' => site_url('backend/calendar/subscription'), 'icon' => 'bi bi-rss'],
            ],
        ]);
    }

    public function feed(): ResponseInterface
    {
        $range = CalendarComponent::range($this->request);

        if ($range === null) {
            return $this->json(['error' => lang('Events.errors.range')], 400);
        }

        $user     = $this->currentUser();
        $category = max(0, (int) $this->request->getGet('category'));
        $service  = new Calendars();
        $shown    = array_values(array_diff(array_keys($service->visibleTo($user)), $service->hidden($user)));
        $rows     = (new EventRepository())->occurrences($user, $range[0], $range[1], false, ['category' => $category, 'calendars' => $shown]);
        $base     = site_url('backend/calendar/event');
        $events   = array_map(static fn (array $row): array => EventRepository::toFeed($row, $base), $rows);

        // Provided events have no category: a category filter shows only own events.
        if ($category === 0) {
            foreach ((new ProvidedEvents())->occurrences($user, $range[0], $range[1], $shown) as $row) {
                $events[] = ProvidedEvents::toFeed($row);
            }
        }

        return $this->json(['events' => $events])->setHeader('Cache-Control', 'private, no-store');
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
            'reminders' => (bool) ($event['allow_reminders'] ?? true),
            'exportUrl' => site_url('backend/calendar/event/' . $code . '/export.ics'),
            'managedBy' => is_string($event['source_module'] ?? null) && $event['source_module'] !== '' ? self::moduleLabel($event['source_module']) : null,
            'sourceUrl' => self::sourceUrl($event['source_url'] ?? null),
            'reminder'  => (new Reminders())->of((int) $event['id'], (int) $user->id),
            'options'   => array_map('intval', (array) config(\Modules\Events\Config\Calendar::class)->reminderOptions),
            'formUrl'   => site_url('backend/calendar/event/' . $code . '/reminder'),
            'backUrl'   => site_url('backend/calendar'),
            'editUrl'   => $this->can('events-events.edit') && ($event['source_module'] ?? null) === null ? site_url('backend/events/edit/' . $code) : null,
        ]);
    }

    /**
     * `GET backend/calendar/event/{code}/export.ics`: a static copy of the event to import.
     */
    public function export(string $code): ResponseInterface
    {
        $event = $this->visible($code, $this->currentUser());

        return IcalExport::download((new IcalFeeds())->single($event, site_url('backend/calendar/event/' . $code)), (string) $event['title'], $this->response);
    }

    /**
     * `POST backend/calendar/calendars` (`calendars[]`: the calendars to **show**): the calendars
     * the user hides in the calendar view. JSON for the component, a redirect without JavaScript.
     */
    public function calendars(): ResponseInterface
    {
        $user    = $this->currentUser();
        $service = new Calendars();
        $shown   = array_map('strval', (array) ($this->request->getPost('calendars') ?? []));
        $hidden  = $service->setHidden($user, array_values(array_diff(array_keys($service->visibleTo($user)), $shown)));

        if ($this->request->isAJAX() || str_contains($this->request->getHeaderLine('Accept'), 'application/json')) {
            return $this->json(['hidden' => $hidden]);
        }

        return redirect()->to(site_url('backend/calendar'))->with('success', lang('Events.calendars.saved'));
    }

    public function reminder(string $code): RedirectResponse
    {
        $user    = $this->currentUser();
        $event   = $this->visible($code, $user);
        $value   = (string) $this->request->getPost('minutes');
        $allowed = array_map('intval', (array) config(\Modules\Events\Config\Calendar::class)->reminderOptions);
        $minutes = $value === '' ? null : (int) $value;

        if ($minutes !== null && (! in_array($minutes, $allowed, true) || ! (bool) ($event['allow_reminders'] ?? true))) {
            return redirect()->to(site_url('backend/calendar/event/' . $code))->with('error', lang('Events.errors.reminder'));
        }

        (new Reminders())->set((int) $event['id'], (int) $user->id, $minutes);

        return redirect()->to(site_url('backend/calendar/event/' . $code))
            ->with('success', lang($minutes === null ? 'Events.reminderRemoved' : 'Events.reminderSaved'));
    }

    /**
     * The name of a module as shown to users ("Managed by News").
     */
    public static function moduleLabel(string $module): string
    {
        $key   = $module . '.module';
        $label = lang($key);

        return $label === $key ? $module : $label;
    }

    /**
     * A safe link back to the source record: an internal path or an http(s) URL, else null.
     */
    public static function sourceUrl(mixed $url): ?string
    {
        if (! is_string($url) || $url === '' || preg_match('/[\s\\\\]/', $url) === 1) {
            return null;
        }

        if (str_starts_with($url, '/') && ! str_starts_with($url, '//')) {
            return site_url(ltrim($url, '/'));
        }

        return preg_match('#^https?://[^/@]+(/|$)#', $url) === 1 ? $url : null;
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
