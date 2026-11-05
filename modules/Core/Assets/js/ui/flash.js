/**
 * Session messages as toasts (component "core/ui/flash", printed by the theme's
 * partials/flash.php; kpcrudy's messages arrive through the same session keys). The alerts are
 * the fallback without JavaScript: once every message is a toast, they are removed.
 */
import { toast } from 'core/ui/toast.js';

const TYPES = ['success', 'info', 'warning', 'danger'];

export function mount(element) {
    for (const alert of element.querySelectorAll('[data-flash]')) {
        const type = TYPES.includes(alert.dataset.flash) ? alert.dataset.flash : 'info';
        const text = alert.querySelector('[data-flash-text]');

        toast((text || alert).textContent.trim(), { type });
    }

    element.remove();
}
