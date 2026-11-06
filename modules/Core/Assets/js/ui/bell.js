/**
 * Notifications bell ([data-component="core/ui/bell"], partials/notifications_bell.php): loads
 * the latest notifications when the dropdown opens, refreshes the unread count every two minutes
 * while the page is visible, and marks everything as read without leaving the page.
 *
 * Options: { url: bell JSON endpoint, readAll: POST endpoint }.
 */
import { t } from 'core/config.js';
import { get, post } from 'core/http.js';

const REFRESH_MS = 120000;

function setCount(element, count) {
    const badge = element.querySelector('[data-bell-count]');
    const button = element.querySelector('[data-bell-button]');

    if (badge !== null) {
        badge.textContent = count > 99 ? '99+' : String(count);
        badge.hidden = count === 0;
    }

    if (button !== null) {
        button.setAttribute('aria-label', t('bell.label', { count }));
    }
}

function item(notification) {
    const link = document.createElement('a');
    link.className = 'list-group-item list-group-item-action d-flex gap-2 py-2';
    link.href = notification.url;

    if (!notification.read) {
        link.classList.add('bell-unread');
    }

    const icon = document.createElement('i');
    icon.className = (notification.icon || 'bi bi-bell') + ' mt-1';
    icon.setAttribute('aria-hidden', 'true');

    const text = document.createElement('div');
    text.className = 'min-w-0';

    const title = document.createElement('div');
    title.className = 'small text-break' + (notification.read ? '' : ' fw-semibold');
    title.textContent = notification.title;
    text.append(title);

    if (notification.body) {
        const body = document.createElement('div');
        body.className = 'small text-body-secondary text-break';
        body.textContent = notification.body;
        text.append(body);
    }

    const date = document.createElement('div');
    date.className = 'small text-body-tertiary';
    date.textContent = notification.date;
    text.append(date);

    link.append(icon, text);

    return link;
}

function message(list, text) {
    const paragraph = document.createElement('p');
    paragraph.className = 'small text-body-secondary px-3 py-3 mb-0';
    paragraph.textContent = text;
    list.replaceChildren(paragraph);
}

export function mount(element, options) {
    const list = element.querySelector('[data-bell-list]');
    const form = element.querySelector('[data-bell-read-all]');

    async function load(render) {
        try {
            const data = await get(options.url, { silent: !render });
            setCount(element, Number(data.unread) || 0);

            if (render && list !== null) {
                const items = Array.isArray(data.items) ? data.items : [];

                if (items.length === 0) {
                    message(list, t('bell.empty'));
                } else {
                    list.replaceChildren(...items.map(item));
                }
            }
        } catch (error) {
            if (render && list !== null) {
                message(list, t('bell.error'));
            }
        }
    }

    element.addEventListener('show.bs.dropdown', () => load(true));

    // Opened before this module was loaded.
    if (element.querySelector('.dropdown-menu.show') !== null) {
        load(true);
    }

    if (form !== null && options.readAll) {
        form.addEventListener('submit', async (event) => {
            event.preventDefault();

            try {
                await post(options.readAll, { json: {} });
                setCount(element, 0);

                for (const unread of element.querySelectorAll('.bell-unread')) {
                    unread.classList.remove('bell-unread');
                    unread.querySelector('.fw-semibold')?.classList.remove('fw-semibold');
                }
            } catch (error) {
                // core/http.js already showed the error.
            }
        });
    }

    window.setInterval(() => {
        if (document.visibilityState === 'visible') {
            load(false);
        }
    }, REFRESH_MS);
}
