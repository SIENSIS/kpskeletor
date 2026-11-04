/**
 * Component registry: markup declares behaviour, JavaScript reacts.
 *
 *   <div data-component="events/calendar" data-options='{"view":"month"}'></div>
 *
 * imports "events/calendar.js" on demand (dynamic import() through the import map, so the URL is
 * versioned) and calls its mount(element, options). Options too big for an attribute go in a
 * JSON data block referenced with data-options-id="<id of a script type=application/json>".
 * Elements added later are mounted too (MutationObserver).
 */
import { jsonData } from 'core/config.js';

const NAME = /^[a-z0-9-]+(?:\/[a-z0-9_-]+)+$/;
const mounted = new WeakSet();
const registry = new Map();

/**
 * Registers a component without a file of its own (or overrides one).
 */
export function register(name, mountFunction) {
    registry.set(name, mountFunction);
}

export function options(element) {
    if (element.dataset.optionsId) {
        const data = jsonData(element.dataset.optionsId);

        return data !== null && typeof data === 'object' ? data : {};
    }

    if (!element.dataset.options) {
        return {};
    }

    try {
        const data = JSON.parse(element.dataset.options);

        return data !== null && typeof data === 'object' ? data : {};
    } catch (error) {
        console.warn('Invalid data-options on component', element.dataset.component, error);

        return {};
    }
}

async function resolve(name) {
    if (registry.has(name)) {
        return registry.get(name);
    }

    const module = await import(name + '.js');

    if (typeof module.mount !== 'function') {
        throw new Error('The component ' + name + ' has no mount() function');
    }

    registry.set(name, module.mount);

    return module.mount;
}

export async function mount(element) {
    if (mounted.has(element)) {
        return;
    }

    const name = element.dataset.component || '';

    if (!NAME.test(name)) {
        console.warn('Invalid component name', name);

        return;
    }

    mounted.add(element);

    try {
        const mountFunction = await resolve(name);
        await mountFunction(element, options(element));
        element.dataset.componentMounted = 'true';
    } catch (error) {
        mounted.delete(element);
        console.warn('The component ' + name + ' could not be mounted', error);
    }
}

export function mountAll(root = document) {
    const elements = [];

    if (root instanceof Element && root.matches('[data-component]')) {
        elements.push(root);
    }

    if (root.querySelectorAll) {
        elements.push(...root.querySelectorAll('[data-component]'));
    }

    return Promise.all(elements.map(mount));
}

export function observe(root = document.body) {
    const observer = new MutationObserver((records) => {
        for (const record of records) {
            for (const node of record.addedNodes) {
                if (node instanceof Element) {
                    mountAll(node);
                }
            }
        }
    });

    observer.observe(root, { childList: true, subtree: true });

    return observer;
}
