/**
 * Copy button of the Devtools recipes: `data-component="devtools/copy"` with
 * `data-copy-target="<id of the code element>"`; copies its text to the clipboard.
 */
export function mount(button, options) {
    button.addEventListener('click', async () => {
        const target = document.getElementById(button.dataset.copyTarget || '');

        if (!target || !navigator.clipboard) {
            return;
        }

        await navigator.clipboard.writeText(target.textContent || '');
        button.setAttribute('title', String(options.copied || ''));
        button.classList.add('btn-success');
        button.classList.remove('btn-outline-secondary');
        window.setTimeout(() => {
            button.classList.remove('btn-success');
            button.classList.add('btn-outline-secondary');
        }, 1500);
    });
}
