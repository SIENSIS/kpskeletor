/**
 * Page configuration printed by the layout in <script type="application/json" id="kp-config">
 * (not executable, allowed by the CSP): base URL, locale, CSRF header and the UI texts used by
 * the scripts (from the language files, so they are translated like any other text).
 */
let cached = null;

export function config() {
    if (cached === null) {
        const element = document.getElementById('kp-config');
        cached = {};

        if (element !== null) {
            try {
                cached = JSON.parse(element.textContent || '{}');
            } catch (error) {
                cached = {};
            }
        }
    }

    return cached;
}

/**
 * A translated UI text: t('confirm.ok'), with {name} placeholders.
 */
export function t(key, params = {}) {
    const texts = config().i18n || {};
    let text = typeof texts[key] === 'string' ? texts[key] : key;

    for (const [name, value] of Object.entries(params)) {
        text = text.split('{' + name + '}').join(String(value));
    }

    return text;
}

export function baseUrl(path = '') {
    const base = config().baseUrl || '/';

    return base.replace(/\/+$/, '/') + String(path).replace(/^\/+/, '');
}

/**
 * Reads a JSON data block (<script type="application/json" id="…">).
 */
export function jsonData(id) {
    const element = document.getElementById(id);

    if (element === null || element.getAttribute('type') !== 'application/json') {
        return null;
    }

    try {
        return JSON.parse(element.textContent || 'null');
    } catch (error) {
        return null;
    }
}
