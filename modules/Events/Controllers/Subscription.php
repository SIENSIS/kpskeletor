<?php

declare(strict_types=1);

namespace Modules\Events\Controllers;

use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\I18n\Time;
use Modules\Core\Controllers\BackendController;
use Modules\Events\Services\Calendars;
use Modules\Events\Services\EventRepository;
use Modules\Events\Services\IcalFeeds;

/**
 * "Calendar subscription" (`backend/calendar/subscription`, `events-calendar.view`, user menu):
 * the user's personal iCal feed (docs/events.md, "iCal").
 *
 * - `GET`: the feed's state (active since, last access, filters), the instructions per client;
 * - `POST …/generate` (`reauth`): creates or regenerates the secret URL and shows it **once**
 *   (it is stored hashed); the previous URL stops working at once;
 * - `POST …/revoke`: disables the feed;
 * - `POST …/filters` (`categories[]`, `calendars[]`; none = all).
 *
 * Every change is written to the activity log.
 */
final class Subscription extends BackendController
{
    public function index(?string $secret = null): string
    {
        $user   = $this->currentUser();
        $feeds  = new IcalFeeds();
        $row    = $feeds->token($user);
        $url    = $secret === null ? null : IcalFeeds::url($secret);

        $this->breadcrumb(lang('Events.calendarTitle'), site_url('backend/calendar'));

        return $this->render('Modules\Events\Views\subscription\index', [
            'title'      => lang('Events.subscription.title'),
            'icon'       => 'bi bi-rss',
            'row'        => $row,
            'active'     => $row !== null && $row['revoked_at'] === null,
            'url'        => $url,
            'webcal'     => $url === null ? null : IcalFeeds::webcal($url),
            'categories' => (new EventRepository())->categories(),
            'calendars'  => array_values((new Calendars())->visibleTo($user)),
            'selectedCategories' => IcalFeeds::ids($row['categories'] ?? null),
            'selectedCalendars'  => Calendars::split($row['calendars'] ?? null),
            'publicUrl'  => (bool) config(\Modules\Events\Config\Calendar::class)->publicCalendar && (bool) config(\Modules\Events\Config\Calendar::class)->publicFeeds
                ? site_url('ical/public/all.ics') : null,
            'lastAccess' => is_string($row['last_access_at'] ?? null) ? Time::parse($row['last_access_at']) : null,
        ]);
    }

    public function generate(): string
    {
        $user    = $this->currentUser();
        $feeds   = new IcalFeeds();
        $existed = $feeds->token($user) !== null;
        $secret  = $feeds->issue($user);

        activity_log('Events', $existed ? 'ical_regenerated' : 'ical_created', ['resource' => 'events-calendar', 'record_id' => (int) $user->id]);

        return $this->index($secret);
    }

    public function revoke(): RedirectResponse
    {
        $user = $this->currentUser();

        if ((new IcalFeeds())->revoke($user)) {
            activity_log('Events', 'ical_revoked', ['resource' => 'events-calendar', 'record_id' => (int) $user->id]);
        }

        return redirect()->to(site_url('backend/calendar/subscription'))->with('success', lang('Events.subscription.revoked'));
    }

    public function filters(): ResponseInterface
    {
        $user  = $this->currentUser();
        $feeds = new IcalFeeds();

        if ($feeds->token($user) === null) {
            return redirect()->to(site_url('backend/calendar/subscription'));
        }

        $feeds->setFilters(
            $user,
            array_map('intval', (array) ($this->request->getPost('categories') ?? [])),
            array_map('strval', (array) ($this->request->getPost('calendars') ?? [])),
        );
        activity_log('Events', 'ical_filters', ['resource' => 'events-calendar', 'record_id' => (int) $user->id]);

        return redirect()->to(site_url('backend/calendar/subscription'))->with('success', lang('Events.subscription.filtersSaved'));
    }
}
