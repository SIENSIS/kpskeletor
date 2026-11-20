/**
 * Web Push on this device (the `push` notification channel, D-44-02).
 *
 * Profile › Notifications: <div data-component="core/push" data-options='{"urls": {…}}'>
 *   - "Activate notifications on this device": asks the browser's permission (only on that
 *     click), registers the service worker (/sw.js, even with the PWA off), subscribes with the
 *     site's VAPID public key and sends the subscription to the server;
 *   - the user's devices with "Send a test", "Rename" and "Remove" ("This device" marks the
 *     browser's own subscription, found by the SHA-256 of its endpoint);
 *   - clear messages when push is not supported, the permission was denied, or on iPhone/iPad
 *     outside the installed PWA (iOS/iPadOS 16.4+ only from the home screen).
 *
 * Logout form: data-component="core/push" data-options='{"mode": "logout", "urls": {…}}' asks
 * whether to keep receiving notifications on this device or to remove it before signing out.
 *
 * The server checks everything again (own devices only, push service hosts only).
 */
import { t } from 'core/config.js';
import { get, post } from 'core/http.js';
import { registerServiceWorker } from 'core/pwa.js';
import { confirm } from 'core/ui/confirm.js';
import { openModal } from 'core/ui/modal.js';
import { toast } from 'core/ui/toast.js';

const STORAGE_KEY = 'kp.push.device';
const SUBSCRIBE_TIMEOUT = 20000;

export function supported() {
    return 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window;
}

export function isAppleMobile() {
    const ua = navigator.userAgent || '';

    return /iPhone|iPad|iPod/.test(ua) || (ua.includes('Macintosh') && navigator.maxTouchPoints > 1);
}

export function isStandalone() {
    return window.navigator.standalone === true || window.matchMedia('(display-mode: standalone)').matches;
}

function remember(hash) {
    try {
        if (hash) {
            window.localStorage.setItem(STORAGE_KEY, hash);
        } else {
            window.localStorage.removeItem(STORAGE_KEY);
        }
    } catch (error) {
        // storage blocked: the logout question is simply not asked
    }
}

function remembered() {
    try {
        return window.localStorage.getItem(STORAGE_KEY) || '';
    } catch (error) {
        return '';
    }
}

export async function sha256(text) {
    const digest = await crypto.subtle.digest('SHA-256', new TextEncoder().encode(text));

    return Array.from(new Uint8Array(digest), (byte) => byte.toString(16).padStart(2, '0')).join('');
}

function keyBytes(base64url) {
    const base64 = (base64url + '='.repeat((4 - (base64url.length % 4)) % 4)).replace(/-/g, '+').replace(/_/g, '/');
    const raw = window.atob(base64);

    return Uint8Array.from(raw, (char) => char.charCodeAt(0));
}

function sameKey(buffer, bytes) {
    if (!buffer) {
        return false;
    }

    const current = new Uint8Array(buffer);

    return current.length === bytes.length && current.every((value, index) => value === bytes[index]);
}

async function currentSubscription() {
    if (!supported()) {
        return null;
    }

    const registration = await navigator.serviceWorker.getRegistration();

    return registration ? registration.pushManager.getSubscription() : null;
}

function url(urls, name, id = '') {
    return String(urls[name] || '') + (id === '' ? '' : String(id));
}

function button(label, icon, variant) {
    const element = document.createElement('button');
    element.type = 'button';
    element.className = 'btn btn-sm btn-' + variant;
    const glyph = document.createElement('i');
    glyph.className = 'bi ' + icon + ' me-1';
    glyph.setAttribute('aria-hidden', 'true');
    element.append(glyph, document.createTextNode(label));

    return element;
}

function mountDevices(element, settings) {
    const urls = settings.urls || {};
    const status = element.querySelector('[data-push-status]');
    const enable = element.querySelector('[data-push-enable]');
    const disable = element.querySelector('[data-push-disable]');
    const list = element.querySelector('[data-push-devices]');
    const empty = element.querySelector('[data-push-empty]');
    let state = { enabled: false, publicKey: '', devices: [] };
    let ownHash = '';

    const say = (message, variant = 'secondary') => {
        status.textContent = message;
        status.className = 'alert alert-' + variant + ' mb-3';
        status.hidden = message === '';
    };

    const render = () => {
        list.replaceChildren();
        empty.hidden = state.devices.length > 0;

        for (const device of state.devices) {
            const item = document.createElement('li');
            item.className = 'list-group-item d-flex flex-wrap align-items-center gap-2';
            item.dataset.deviceId = String(device.id);

            const info = document.createElement('div');
            info.className = 'me-auto text-break';
            const name = document.createElement('div');
            name.className = 'fw-semibold';
            name.textContent = device.label;

            if (device.hash === ownHash) {
                const badge = document.createElement('span');
                badge.className = 'badge text-bg-primary ms-2';
                badge.textContent = t('push.thisDevice');
                name.append(badge);
            }

            const meta = document.createElement('div');
            meta.className = 'small text-body-secondary';
            meta.textContent = t('push.added', { date: device.created }) + (device.lastUsed ? ' · ' + t('push.lastUsed', { date: device.lastUsed }) : '');
            info.append(name, meta);

            const actions = document.createElement('div');
            actions.className = 'd-flex flex-wrap gap-2';
            const test = button(t('push.test'), 'bi-send', 'outline-primary');
            const rename = button(t('push.rename'), 'bi-pencil', 'outline-secondary');
            const remove = button(t('push.remove'), 'bi-trash', 'outline-danger');
            test.setAttribute('aria-label', t('push.test') + ': ' + device.label);
            rename.setAttribute('aria-label', t('push.rename') + ': ' + device.label);
            remove.setAttribute('aria-label', t('push.remove') + ': ' + device.label);
            actions.append(test, rename, remove);

            const form = document.createElement('form');
            // d-none/d-flex, not `hidden`: Bootstrap's d-flex would win over the attribute.
            form.className = 'w-100 gap-2 d-none';
            const input = document.createElement('input');
            input.type = 'text';
            input.className = 'form-control form-control-sm';
            input.maxLength = 100;
            input.required = true;
            input.value = device.label;
            input.setAttribute('aria-label', t('push.newName'));
            const save = button(t('push.save'), 'bi-check2', 'primary');
            save.type = 'submit';
            form.append(input, save);

            test.addEventListener('click', () => act(() => post(url(urls, 'test', device.id), { json: {} })));
            remove.addEventListener('click', () => act(async () => {
                if (!(await confirm(t('push.removeConfirm'), { variant: 'danger' }))) {
                    return null;
                }

                const answer = await post(url(urls, 'delete', device.id), { json: {} });

                if (device.hash === ownHash) {
                    const subscription = await currentSubscription();

                    if (subscription) {
                        await subscription.unsubscribe();
                    }

                    remember('');
                }

                return answer;
            }));
            rename.setAttribute('aria-expanded', 'false');
            rename.addEventListener('click', () => {
                const open = form.classList.contains('d-none');
                form.classList.toggle('d-none', !open);
                form.classList.toggle('d-flex', open);
                rename.setAttribute('aria-expanded', open ? 'true' : 'false');

                if (open) {
                    input.focus();
                }
            });
            form.addEventListener('submit', (event) => {
                event.preventDefault();
                act(() => post(url(urls, 'label', device.id), { json: { label: input.value } }));
            });

            item.append(info, actions, form);
            list.append(item);
        }
    };

    const refreshState = async (keepStatus = false) => {
        const subscription = await currentSubscription().catch(() => null);
        ownHash = subscription ? await sha256(subscription.endpoint) : '';
        const own = state.devices.some((device) => device.hash === ownHash);
        remember(own ? ownHash : '');
        render();

        if (!state.enabled) {
            enable.hidden = true;
            disable.hidden = true;
            say(t('push.off'), 'warning');

            return;
        }

        if (!supported()) {
            enable.hidden = true;
            disable.hidden = true;
            say(isAppleMobile() ? t('push.ios') : t('push.unsupported'), 'warning');

            return;
        }

        if (Notification.permission === 'denied') {
            enable.hidden = true;
            disable.hidden = true;
            say(t('push.denied'), 'warning');

            return;
        }

        enable.hidden = own;
        disable.hidden = !own;

        if (!keepStatus) {
            say(own ? t('push.active') : t('push.inactive'), own ? 'success' : 'secondary');
        }
    };

    const act = async (action) => {
        // An action that answers null has already said why it stopped (permission, failure).
        let keepStatus = false;
        element.setAttribute('aria-busy', 'true');
        enable.disabled = true;
        disable.disabled = true;

        try {
            const answer = await action();
            keepStatus = answer === null;

            if (answer && typeof answer === 'object') {
                if (Array.isArray(answer.devices)) {
                    state = answer;
                }

                if (answer.message) {
                    toast(answer.message, { type: 'success' });
                }
            }
        } catch (error) {
            if (error && error.data && Array.isArray(error.data.devices)) {
                state = error.data;
            }
        }

        await refreshState(keepStatus);
        element.removeAttribute('aria-busy');
        enable.disabled = false;
        disable.disabled = false;
    };

    enable.addEventListener('click', () => act(async () => {
        say(t('push.working'), 'secondary');

        if (isAppleMobile() && !isStandalone()) {
            say(t('push.ios'), 'warning');

            return null;
        }

        const permission = await Notification.requestPermission();

        if (permission !== 'granted') {
            say(permission === 'denied' ? t('push.denied') : t('push.dismissed'), 'warning');

            return null;
        }

        await registerServiceWorker();
        const registration = await navigator.serviceWorker.ready;
        const key = keyBytes(state.publicKey);
        let subscription = await registration.pushManager.getSubscription();

        // Keys rotated (push:keys): a subscription bound to the old key no longer works.
        if (subscription && !sameKey(subscription.options.applicationServerKey, key)) {
            await subscription.unsubscribe();
            subscription = null;
        }

        if (!subscription) {
            try {
                // A browser that cannot reach its push service may never answer.
                subscription = await Promise.race([
                    registration.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: key }),
                    new Promise((resolve, reject) => { window.setTimeout(() => reject(new Error('timeout')), SUBSCRIBE_TIMEOUT); }),
                ]);
            } catch (error) {
                say(t('push.failed'), 'danger');

                return null;
            }
        }

        return post(url(urls, 'subscribe'), { json: subscription.toJSON() });
    }));

    disable.addEventListener('click', () => act(async () => {
        const device = state.devices.find((item) => item.hash === ownHash);
        const answer = device ? await post(url(urls, 'delete', device.id), { json: {} }) : null;
        const subscription = await currentSubscription();

        if (subscription) {
            await subscription.unsubscribe();
        }

        remember('');

        return answer;
    }));

    act(() => get(url(urls, 'state')));
}

function mountLogout(form, settings) {
    const urls = settings.urls || {};

    form.addEventListener('submit', async (event) => {
        if (form.dataset.pushAsked === 'true' || remembered() === '' || !supported()) {
            return;
        }

        event.preventDefault();
        const choice = await openModal({
            title: t('push.logoutTitle'),
            body: t('push.logoutBody'),
            buttons: [
                { label: t('push.logoutRemove'), value: 'remove', variant: 'outline-danger' },
                { label: t('push.logoutKeep'), value: 'keep', variant: 'primary', autofocus: true },
            ],
        });

        if (choice === null) {
            return;
        }

        if (choice === 'remove') {
            try {
                const hash = remembered();
                const state = await get(url(urls, 'state'), { silent: true });
                const device = (state.devices || []).find((item) => item.hash === hash);

                if (device) {
                    await post(url(urls, 'delete', device.id), { json: {}, silent: true });
                }

                const subscription = await currentSubscription();

                if (subscription) {
                    await subscription.unsubscribe();
                }
            } catch (error) {
                // signing out goes on; the device can be removed from another session
            }

            remember('');
        }

        form.dataset.pushAsked = 'true';
        form.requestSubmit();
    });
}

export function mount(element, settings = {}) {
    if (settings.mode === 'logout') {
        mountLogout(element, settings);
    } else {
        mountDevices(element, settings);
    }
}
