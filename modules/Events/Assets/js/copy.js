/**
 * Copy button: <button data-component="events/copy" data-options='{"target": "<input id>", "copied": "…"}'>.
 *
 * Copies the value of the target input to the clipboard (or selects it when the clipboard is not
 * available, so the user can copy it by hand).
 */
import { toast } from 'core/ui/toast.js';

export function mount(element, options) {
    const input = document.getElementById(options.target || '');

    if (!input) {
        return;
    }

    element.addEventListener('click', () => {
        input.select();

        if (navigator.clipboard) {
            navigator.clipboard.writeText(input.value).then(
                () => toast(options.copied || '', { type: 'success' }),
                () => input.select(),
            );
        }
    });
}
