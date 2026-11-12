/**
 * Calendar component: <div data-component="events/calendar" data-options='{…}'>.
 *
 * Loads the vendored FullCalendar 7 (MIT, public/assets/vendor/fullcalendar) on demand from the
 * URLs in the options, then shows the month, week, day and list views. FullCalendar 7 ships its
 * CSS as files and only touches styles through the CSSOM, so it works under the strict CSP.
 *
 * Options (Modules\Events\Services\CalendarComponent::options()):
 *   feed      JSON feed URL (start/end dates of the visible range are added, plus `category`)
 *   scripts   library, theme and locale scripts, loaded in order
 *   styles    stylesheets (the library's and events::css/calendar.css)
 *   locale    page language (ca, es, en)
 *   view      first view on larger screens; phones start with the list
 *   category  select element id of the category filter (optional)
 *   error     text shown when the library or the events cannot be loaded
 *
 * Events are links to their details page (the feed's `url`): a click opens it like any link.
 * Dates are the site's wall-clock times (the feed sends them without offset and the calendar
 * runs in UTC, so a browser in another time zone shows the same times as the site).
 */
import { get } from 'core/http.js';

const loading = new Map();

function load(tag, url) {
    if (!loading.has(url)) {
        loading.set(url, new Promise((resolve, reject) => {
            const element = document.createElement(tag);

            if (tag === 'link') {
                element.rel = 'stylesheet';
                element.href = url;
            } else {
                element.src = url;
                element.async = false;
            }

            element.addEventListener('load', () => resolve());
            element.addEventListener('error', () => reject(new Error('Cannot load ' + url)));
            document.head.appendChild(element);
        }));
    }

    return loading.get(url);
}

async function loadLibrary(options) {
    await Promise.all((options.styles || []).map((url) => load('link', url)));

    for (const url of options.scripts || []) {
        await load('script', url);
    }

    if (typeof window.FullCalendar === 'undefined') {
        throw new Error('FullCalendar is not available');
    }

    return window.FullCalendar;
}

function isPhone() {
    return window.matchMedia('(max-width: 575.98px)').matches;
}

function dateOnly(date) {
    return date.toISOString().slice(0, 10);
}

export async function mount(element, options) {
    const status = element.querySelector('[data-calendar-status]');
    let library;

    try {
        library = await loadLibrary(options);
    } catch (error) {
        if (status) {
            status.textContent = options.error || '';
            status.classList.remove('visually-hidden');
        }

        console.error(error);

        return;
    }

    const target = document.createElement('div');
    target.className = 'events-calendar';
    element.appendChild(target);

    const filter = options.category ? document.getElementById(options.category) : null;

    const calendar = new library.Calendar(target, {
        locale: options.locale || 'ca',
        timeZone: 'UTC',
        initialView: isPhone() ? 'listMonth' : (options.view || 'dayGridMonth'),
        headerToolbar: isPhone()
            ? { start: 'prev,next', center: 'title', end: 'today' }
            : { start: 'prev,next today', center: 'title', end: 'dayGridMonth,timeGridWeek,timeGridDay,listMonth' },
        footerToolbar: isPhone() ? { center: 'listMonth,dayGridMonth,timeGridDay' } : false,
        height: 'auto',
        nowIndicator: true,
        dayMaxEvents: true,
        navLinks: true,
        eventDisplay: 'block',
        events(info, success, failure) {
            const params = new URLSearchParams({ start: dateOnly(info.start), end: dateOnly(info.end) });

            if (filter && filter.value !== '') {
                params.set('category', filter.value);
            }

            get(options.feed + '?' + params.toString(), { silent: true })
                .then((data) => success(Array.isArray(data.events) ? data.events : []))
                .catch((error) => {
                    if (status) {
                        status.textContent = options.error || '';
                        status.classList.remove('visually-hidden');
                    }

                    failure(error);
                });
        },
    });

    calendar.render();

    if (filter) {
        filter.addEventListener('change', () => calendar.refetchEvents());
    }
}
