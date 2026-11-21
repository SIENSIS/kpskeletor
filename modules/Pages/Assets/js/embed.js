/**
 * Embedded content with "click to load" (`data-component="pages/embed"`, Views/sections/embed.php):
 * nothing is requested from the other site until the visitor presses the button; then the iframe
 * is built from the data attributes (address, title, sandbox, allow) with `loading="lazy"` and
 * `referrerpolicy`, and receives the focus. Sections without the placeholder already have their
 * iframe (nothing to do).
 */
export function mount(element) {
    const button = element.querySelector('[data-embed-load]');
    const placeholder = element.querySelector('[data-embed-placeholder]');

    if (!button || !placeholder) {
        return;
    }

    button.addEventListener('click', () => {
        const src = element.dataset.src || '';

        if (!src.startsWith('https://')) {
            return;
        }

        const frame = document.createElement('iframe');
        frame.src = src;
        frame.title = element.dataset.title || '';
        frame.loading = 'lazy';
        frame.referrerPolicy = 'strict-origin-when-cross-origin';
        frame.setAttribute('sandbox', element.dataset.sandbox || '');
        frame.tabIndex = -1;

        if (element.dataset.allow) {
            frame.setAttribute('allow', element.dataset.allow);
            frame.allowFullscreen = true;
        }

        placeholder.replaceWith(frame);
        frame.focus();
    });
}
