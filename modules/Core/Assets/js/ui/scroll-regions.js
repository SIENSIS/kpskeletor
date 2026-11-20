/**
 * Keyboard access to horizontally scrolled regions (WCAG 2.1.1; axe `scrollable-region-focusable`):
 * a `.table-responsive` (or `[data-scroll-region]`) whose content is wider than the box gets
 * `tabindex="0"`, so keyboard users can focus it and scroll with the arrow keys. Regions that
 * fit lose it again when the window is resized. Regions that already contain focusable elements
 * are reachable anyway and are left alone.
 */
const SELECTOR = '.table-responsive, [data-scroll-region]';
// Hidden and disabled controls cannot take the focus (a table of disabled checkboxes still needs it).
const FOCUSABLE = 'a[href], button:not([disabled]), input:not([type="hidden"]):not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';

function update(region) {
    const scrolls = region.scrollWidth > region.clientWidth;
    const reachable = region.querySelector(FOCUSABLE) !== null;

    if (scrolls && !reachable) {
        if (!region.hasAttribute('tabindex')) {
            region.setAttribute('tabindex', '0');
            region.dataset.scrollRegion = 'auto';
        }
    } else if (region.dataset.scrollRegion === 'auto') {
        region.removeAttribute('tabindex');
        delete region.dataset.scrollRegion;
    }
}

export function initScrollRegions(root = document) {
    const regions = () => root.querySelectorAll(SELECTOR);
    regions().forEach(update);

    let pending = 0;
    window.addEventListener('resize', () => {
        window.cancelAnimationFrame(pending);
        pending = window.requestAnimationFrame(() => regions().forEach(update));
    });
}
