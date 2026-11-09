/**
 * Async dashboard widget (`data-component="core/ui/widget"`, printed by the Core's
 * WidgetRenderer): loads `data-url` (JSON `{data, html}`), puts `html` in the card and refreshes
 * it every `data-refresh` seconds while the page is visible. Loading and error states are shown in
 * the card; the server isolates failures (500 with a generic message).
 *
 * Widgets that draw their data themselves (charts…) use their own component and reuse `load()`:
 *
 *   import { load } from 'core/ui/widget.js';
 *   export function mount(root) {
 *       load(root, (response, content) => { content.textContent = response.data.total; });
 *   }
 */
import { get } from 'core/http.js';
import { t } from 'core/config.js';

function showError(content, message) {
    const box = document.createElement('div');
    box.className = 'd-flex align-items-start gap-2 text-danger-emphasis py-2';
    box.setAttribute('role', 'alert');

    const icon = document.createElement('i');
    icon.className = 'bi bi-exclamation-triangle';
    icon.setAttribute('aria-hidden', 'true');

    const text = document.createElement('span');
    text.textContent = message;

    box.append(icon, text);
    content.replaceChildren(box);
}

/**
 * Loads the widget's data now and on every refresh; `render(response, content)` shows it.
 */
export function load(root, render) {
    const url = root.dataset.url || '';
    const refresh = Number.parseInt(root.dataset.refresh || '0', 10);
    const content = root.querySelector('[data-widget-content]') || root;

    if (url === '') {
        return;
    }

    const run = async () => {
        root.setAttribute('aria-busy', 'true');

        try {
            const response = await get(url, { silent: true });
            render(response && typeof response === 'object' ? response : {}, content);
        } catch (error) {
            showError(content, error && error.status === 403 ? t('http.forbidden') : t('widget.error'));
        } finally {
            root.setAttribute('aria-busy', 'false');
        }
    };

    run();

    if (refresh > 0) {
        window.setInterval(() => {
            if (document.visibilityState === 'visible' && root.isConnected) {
                run();
            }
        }, Math.max(refresh, 15) * 1000);
    }
}

/**
 * Nodes of the HTML built (and escaped) by the server. Parsed in an inert document (no scripts
 * run, no resources load while parsing; the CSP blocks inline code anyway), never with innerHTML.
 */
function fragment(html) {
    const parsed = new DOMParser().parseFromString(html, 'text/html');
    parsed.querySelectorAll('script, style, iframe, object, embed').forEach((node) => node.remove());

    return Array.from(parsed.body.childNodes).map((node) => document.importNode(node, true));
}

/** Default renderer: the server's HTML. */
export function mount(root) {
    load(root, (response, content) => {
        if (typeof response.html === 'string' && response.html !== '') {
            content.replaceChildren(...fragment(response.html));
        } else {
            content.textContent = t('widget.empty');
        }
    });
}
