/**
 * Back-office sidebar: an offcanvas on phones (Bootstrap), an icon-only bar on tablets and a full
 * sidebar on desktops. The toggle button ([data-sidebar-toggle]) switches between icon-only and
 * full from 768 px up and the choice is remembered in the kp_sidebar cookie, so the server
 * renders the same state on the next page (no flash).
 */
import { setCookie } from 'core/cookies.js';

const DESKTOP = window.matchMedia('(min-width: 1200px)');

function isCollapsed(body) {
    if (body.classList.contains('sidebar-collapsed')) {
        return true;
    }

    return !body.classList.contains('sidebar-expanded') && !DESKTOP.matches;
}

function sync(body, buttons) {
    const collapsed = isCollapsed(body);

    for (const button of buttons) {
        button.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
    }
}

export function initSidebar(root = document) {
    const body = root.body;
    const buttons = [...root.querySelectorAll('[data-sidebar-toggle]')];

    if (buttons.length === 0) {
        return;
    }

    sync(body, buttons);
    DESKTOP.addEventListener('change', () => sync(body, buttons));

    for (const button of buttons) {
        button.addEventListener('click', () => {
            const collapse = !isCollapsed(body);
            body.classList.toggle('sidebar-collapsed', collapse);
            body.classList.toggle('sidebar-expanded', !collapse);
            setCookie('kp_sidebar', collapse ? 'collapsed' : 'expanded');
            sync(body, buttons);
        });
    }
}
