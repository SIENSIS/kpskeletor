/**
 * Preference cookies written by the scripts (theme mode, sidebar, cookie notice). They hold no
 * personal data, are readable by the server (to render without a flash) and are SameSite=Lax;
 * Secure on HTTPS.
 */
export function getCookie(name) {
    const prefix = name + '=';

    for (const part of document.cookie.split(';')) {
        const item = part.trim();

        if (item.startsWith(prefix)) {
            return decodeURIComponent(item.slice(prefix.length));
        }
    }

    return null;
}

export function setCookie(name, value, days = 365) {
    const secure = window.location.protocol === 'https:' ? '; Secure' : '';
    const maxAge = Math.round(days * 86400);

    document.cookie = name + '=' + encodeURIComponent(value) + '; Max-Age=' + maxAge + '; Path=/; SameSite=Lax' + secure;
}
