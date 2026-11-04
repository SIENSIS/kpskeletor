/**
 * Toasts (Bootstrap): toast('Saved', { type: 'success' }). Types: success, danger, warning, info.
 * Text only (never HTML). Danger toasts are announced at once (role="alert").
 */
import { t } from 'core/config.js';

const TYPES = { success: 'bi-check-circle', danger: 'bi-exclamation-octagon', warning: 'bi-exclamation-triangle', info: 'bi-info-circle' };

function container() {
    let element = document.getElementById('kp-toasts');

    if (element === null) {
        element = document.createElement('div');
        element.id = 'kp-toasts';
        element.className = 'toast-container position-fixed bottom-0 end-0 p-3';
        element.setAttribute('aria-live', 'polite');
        element.setAttribute('aria-atomic', 'false');
        document.body.append(element);
    }

    return element;
}

export function toast(message, settings = {}) {
    const type = Object.hasOwn(TYPES, settings.type) ? settings.type : 'info';
    const element = document.createElement('div');
    element.className = 'toast align-items-center border-0 toast-' + type;
    element.setAttribute('role', type === 'danger' ? 'alert' : 'status');
    element.setAttribute('aria-atomic', 'true');

    const row = document.createElement('div');
    row.className = 'd-flex';

    const body = document.createElement('div');
    body.className = 'toast-body d-flex align-items-start gap-2';

    const icon = document.createElement('i');
    icon.className = 'bi ' + TYPES[type];
    icon.setAttribute('aria-hidden', 'true');

    const text = document.createElement('span');
    text.textContent = String(message);
    body.append(icon, text);

    const close = document.createElement('button');
    close.type = 'button';
    close.className = 'btn-close me-2 m-auto';
    close.setAttribute('data-bs-dismiss', 'toast');
    close.setAttribute('aria-label', t('ui.close'));

    row.append(body, close);
    element.append(row);
    container().append(element);

    const delay = Number.isFinite(settings.delay) ? settings.delay : (type === 'danger' ? 8000 : 5000);

    if (window.bootstrap && window.bootstrap.Toast) {
        const instance = window.bootstrap.Toast.getOrCreateInstance(element, { delay, autohide: delay > 0 });
        element.addEventListener('hidden.bs.toast', () => element.remove());
        instance.show();
    } else {
        element.classList.add('show');
        window.setTimeout(() => element.remove(), delay);
    }

    return element;
}
