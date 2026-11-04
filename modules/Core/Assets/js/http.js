/**
 * fetch() wrapper for same-origin requests: sends the CSRF header on unsafe methods, takes the
 * regenerated token from the response, parses JSON and turns JSON errors into toasts.
 *
 *   import { get, post } from 'core/http.js';
 *   const data = await post('backend/news/posts/1/publish', { json: { at: '2026-01-01' } });
 */
import { baseUrl, t } from 'core/config.js';
import { toast } from 'core/ui/toast.js';

export class HttpError extends Error {
    constructor(message, status, data) {
        super(message);
        this.name = 'HttpError';
        this.status = status;
        this.data = data;
    }
}

function csrf() {
    const header = document.querySelector('meta[name="csrf-header"]');
    const token = document.querySelector('meta[name="csrf-token"]');

    return header && token ? { header: header.content, token: token.content, element: token } : null;
}

function errorMessage(data, status) {
    if (data && typeof data === 'object') {
        if (typeof data.message === 'string' && data.message !== '') {
            return data.message;
        }

        if (data.messages && typeof data.messages === 'object') {
            const messages = Object.values(data.messages).filter((m) => typeof m === 'string');

            if (messages.length > 0) {
                return messages.join(' ');
            }
        }

        if (typeof data.error === 'string' && data.error !== '') {
            return data.error;
        }
    }

    return status === 403 ? t('http.forbidden') : t('http.error');
}

export async function request(url, settings = {}) {
    const method = (settings.method || 'GET').toUpperCase();
    const target = new URL(url, baseUrl());

    if (target.origin !== window.location.origin) {
        throw new HttpError(t('http.crossOrigin'), 0, null);
    }

    const headers = new Headers(settings.headers || {});
    headers.set('X-Requested-With', 'XMLHttpRequest');

    if (!headers.has('Accept')) {
        headers.set('Accept', 'application/json');
    }

    let body = settings.body;

    if (settings.json !== undefined) {
        headers.set('Content-Type', 'application/json');
        body = JSON.stringify(settings.json);
    }

    const token = csrf();

    if (token !== null && !['GET', 'HEAD', 'OPTIONS'].includes(method)) {
        headers.set(token.header, token.token);
    }

    let response;

    try {
        response = await fetch(target, { method, headers, body, credentials: 'same-origin', redirect: 'follow' });
    } catch (error) {
        if (!settings.silent) {
            toast(t('http.network'), { type: 'danger' });
        }

        throw new HttpError(t('http.network'), 0, null);
    }

    if (token !== null) {
        const fresh = response.headers.get(token.header);

        if (fresh) {
            token.element.content = fresh;
        }
    }

    const type = response.headers.get('Content-Type') || '';
    let data = null;

    if (type.includes('json')) {
        try {
            data = await response.json();
        } catch (error) {
            data = null;
        }
    } else if (method !== 'HEAD') {
        data = await response.text();
    }

    if (!response.ok) {
        const message = errorMessage(data, response.status);

        if (!settings.silent) {
            toast(message, { type: 'danger' });
        }

        throw new HttpError(message, response.status, data);
    }

    return data;
}

export const get = (url, settings = {}) => request(url, { ...settings, method: 'GET' });
export const post = (url, settings = {}) => request(url, { ...settings, method: 'POST' });
export const put = (url, settings = {}) => request(url, { ...settings, method: 'PUT' });
export const patch = (url, settings = {}) => request(url, { ...settings, method: 'PATCH' });
export const del = (url, settings = {}) => request(url, { ...settings, method: 'DELETE' });
