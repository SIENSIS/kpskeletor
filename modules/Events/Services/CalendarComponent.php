<?php

declare(strict_types=1);

namespace Modules\Events\Services;

use CodeIgniter\HTTP\IncomingRequest;
use DateTimeImmutable;
use DateTimeZone;

/**
 * Shared pieces of the calendar pages (back office and public site): the options of the
 * `events/calendar` component and the date range of a feed request.
 */
final class CalendarComponent
{
    /** Views the library offers (`Config\Calendar::$defaultView` must be one of them). */
    public const VIEWS = ['dayGridMonth', 'timeGridWeek', 'timeGridDay', 'listMonth'];

    /**
     * Options of `<div data-component="events/calendar">`: the feed, the vendored library and the
     * page's language.
     *
     * @return array<string, mixed>
     */
    public static function options(string $feedUrl, ?string $categorySelectId = null): array
    {
        helper('asset');

        $locale  = service('request')->getLocale();
        $scripts = [asset('vendor::fullcalendar/global.js'), asset('vendor::fullcalendar/themes/classic/global.js')];

        if (in_array($locale, ['ca', 'es'], true)) {
            $scripts[] = asset('vendor::fullcalendar/locales/' . $locale . '.global.js');
        }

        $view = (string) config(\Modules\Events\Config\Calendar::class)->defaultView;

        return [
            'feed'     => $feedUrl,
            'scripts'  => $scripts,
            'styles'   => [
                asset('vendor::fullcalendar/skeleton.css'),
                asset('vendor::fullcalendar/themes/classic/theme.css'),
                asset('vendor::fullcalendar/themes/classic/palette.css'),
                asset('events::css/calendar.css'),
            ],
            'locale'   => $locale,
            'view'     => in_array($view, self::VIEWS, true) ? $view : 'dayGridMonth',
            'category' => $categorySelectId,
            'error'    => lang('Events.calendarError'),
        ];
    }

    /**
     * The range `[start, end)` of a feed request (`?start=YYYY-MM-DD&end=YYYY-MM-DD`, dates in the
     * site's time zone), or null when it is missing, invalid or longer than
     * `Config\Calendar::$maxRangeDays`.
     *
     * @return array{0: DateTimeImmutable, 1: DateTimeImmutable}|null
     */
    public static function range(IncomingRequest $request): ?array
    {
        $zone  = new DateTimeZone(site_timezone());
        $dates = [];

        foreach (['start', 'end'] as $name) {
            $value = substr((string) $request->getGet($name), 0, 10);
            $date  = DateTimeImmutable::createFromFormat('!Y-m-d', $value, $zone);

            if ($date === false || $date->format('Y-m-d') !== $value) {
                return null;
            }

            $dates[] = $date;
        }

        [$start, $end] = $dates;
        $days          = (int) $start->diff($end)->format('%r%a');

        return $days < 1 || $days > (int) config(\Modules\Events\Config\Calendar::class)->maxRangeDays ? null : [$start, $end];
    }
}
