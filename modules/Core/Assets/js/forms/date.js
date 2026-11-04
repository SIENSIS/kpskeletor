/**
 * Date inputs: <input type="date" data-component="core/forms/date" data-options='{"min":"today"}'>
 * sets min/max ("today", "+7d", "-1y" or YYYY-MM-DD) so phones show the native picker with the
 * right range. formatDate() formats a date in the page's locale.
 */
import { config } from 'core/config.js';

function pad(value) {
    return String(value).padStart(2, '0');
}

export function isoDate(date) {
    return date.getFullYear() + '-' + pad(date.getMonth() + 1) + '-' + pad(date.getDate());
}

export function resolveDate(value, now = new Date()) {
    if (typeof value !== 'string' || value === '') {
        return null;
    }

    if (/^\d{4}-\d{2}-\d{2}$/.test(value)) {
        return value;
    }

    if (value === 'today') {
        return isoDate(now);
    }

    const match = /^([+-]\d+)([dmy])$/.exec(value);

    if (match === null) {
        return null;
    }

    const date = new Date(now.getTime());
    const amount = Number(match[1]);

    if (match[2] === 'd') {
        date.setDate(date.getDate() + amount);
    } else if (match[2] === 'm') {
        date.setMonth(date.getMonth() + amount);
    } else {
        date.setFullYear(date.getFullYear() + amount);
    }

    return isoDate(date);
}

export function formatDate(value, style = 'medium') {
    const date = value instanceof Date ? value : new Date(value);

    if (Number.isNaN(date.getTime())) {
        return '';
    }

    return new Intl.DateTimeFormat(config().locale || document.documentElement.lang || undefined, { dateStyle: style }).format(date);
}

export function mount(input, options = {}) {
    if (!(input instanceof HTMLInputElement)) {
        return;
    }

    const min = resolveDate(options.min);
    const max = resolveDate(options.max);

    if (min !== null) {
        input.min = min;
    }

    if (max !== null) {
        input.max = max;
    }
}
