/**
 * Remembers that the cookie notice was closed (kp_cookies cookie; the server stops printing it).
 */
import { setCookie } from 'core/cookies.js';

export function initCookieNotice(root = document) {
    for (const notice of root.querySelectorAll('[data-cookie-notice]')) {
        notice.addEventListener('closed.bs.alert', () => setCookie('kp_cookies', '1'));
    }
}
