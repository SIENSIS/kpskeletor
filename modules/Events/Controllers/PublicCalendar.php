<?php

declare(strict_types=1);

namespace Modules\Events\Controllers;

use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\ResponseInterface;
use Modules\Core\Controllers\FrontendController;
use Modules\Events\Services\CalendarComponent;
use Modules\Events\Services\EventDetails;
use Modules\Events\Services\EventRepository;
use Modules\Events\Services\IcalExport;
use Modules\Events\Services\IcalFeeds;

/**
 * The public calendar: **only `public` events**, whoever is signed in (private and restricted
 * events are seen in the back office's calendar), while `Config\Calendar::$publicCalendar` is on
 * (otherwise every page is a 404).
 *
 * - `GET calendar` (`?category=<slug>`);
 * - `GET calendar/feed?start=&end=[&category=<id>]` (JSON, rate limit `feeds`);
 * - `GET calendar/event/{code}[?date=YYYY-MM-DD]`, `calendar/event/{code}/export.ics`.
 */
final class PublicCalendar extends FrontendController
{
    public function index(): string
    {
        $this->enabled();

        $repository = new EventRepository();
        $slug       = (string) $this->request->getGet('category');
        $category   = $slug === '' ? null : $repository->categoryBySlug($slug);

        $this->meta(['description' => lang('Events.publicDescription'), 'canonical' => site_url('calendar')]);

        return $this->render('Modules\Events\Views\public\index', [
            'title'      => lang('Events.publicTitle'),
            'categories' => $repository->categories(),
            'selected'   => $category['id'] ?? 0,
            'options'    => CalendarComponent::options(site_url('calendar/feed'), 'events-category'),
        ]);
    }

    public function feed(): ResponseInterface
    {
        $this->enabled();

        $range = CalendarComponent::range($this->request);

        if ($range === null) {
            return $this->response->setStatusCode(400)->setJSON(['error' => lang('Events.errors.range')]);
        }

        $rows = (new EventRepository())->occurrences(null, $range[0], $range[1], true, [
            'category' => max(0, (int) $this->request->getGet('category')),
        ]);
        $base = site_url('calendar/event');

        return $this->response->setJSON(['events' => array_map(static fn (array $row): array => EventRepository::toFeed($row, $base), $rows)])
            ->setHeader('Cache-Control', 'public, max-age=300');
    }

    public function show(string $code): string
    {
        $this->enabled();

        $id    = EventRepository::idFromCode($code);
        $event = $id === null ? null : (new EventRepository())->find(null, $id, true);

        if ($event === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        $date = $this->request->getGet('date');

        $this->meta(['canonical' => site_url('calendar/event/' . $code)]);

        return $this->render('Modules\Events\Views\public\show', [
            'title'       => (string) $event['title'],
            'event'       => EventDetails::build($event, is_string($date) ? $date : null),
            'exportUrl'   => site_url('calendar/event/' . $code . '/export.ics'),
            'breadcrumbs' => [['label' => lang('Events.publicTitle'), 'url' => site_url('calendar')], ['label' => (string) $event['title']]],
        ]);
    }

    /**
     * `GET calendar/event/{code}/export.ics`: a public event as a static iCal copy.
     */
    public function export(string $code): ResponseInterface
    {
        $this->enabled();

        $id    = EventRepository::idFromCode($code);
        $event = $id === null ? null : (new EventRepository())->find(null, $id, true);

        if ($event === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        return IcalExport::download((new IcalFeeds())->single($event, site_url('calendar/event/' . $code)), (string) $event['title'], $this->response);
    }

    private function enabled(): void
    {
        if (! (bool) config(\Modules\Events\Config\Calendar::class)->publicCalendar) {
            throw PageNotFoundException::forPageNotFound();
        }
    }
}
