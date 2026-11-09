/**
 * Permission matrix (`data-component="users/matrix"`): in each cell, "all" (the resource
 * wildcard) ticks and locks the operations, and the quick selections only tick checkboxes
 * ("read only": list, show and view; "none": nothing). The server stores only what is ticked
 * (atomic names and wildcards) and adds the implied permissions.
 */
const READ_ONLY = ['list', 'show', 'view'];

function sync(cell) {
    const all = cell.querySelector('[data-matrix-all]');
    const wildcard = all instanceof HTMLInputElement && all.checked;

    cell.querySelectorAll('[data-matrix-op]').forEach((input) => {
        if (!(input instanceof HTMLInputElement) || input.hasAttribute('data-matrix-locked')) {
            return;
        }

        input.disabled = wildcard;

        if (wildcard) {
            input.checked = true;
            input.removeAttribute('name');
        } else {
            input.name = input.dataset.field ?? '';
        }
    });
}

export function mount(root) {
    root.querySelectorAll('[data-matrix-cell]').forEach((cell) => {
        const all = cell.querySelector('[data-matrix-all]');

        if (all instanceof HTMLInputElement && !all.disabled) {
            all.addEventListener('change', () => {
                if (!all.checked) {
                    cell.querySelectorAll('[data-matrix-op]').forEach((input) => {
                        if (input instanceof HTMLInputElement) {
                            input.checked = false;
                        }
                    });
                }
                sync(cell);
            });
        }

        cell.querySelectorAll('[data-matrix-quick]').forEach((button) => {
            button.addEventListener('click', () => {
                const quick = button.getAttribute('data-matrix-quick');

                if (all instanceof HTMLInputElement) {
                    all.checked = false;
                }

                cell.querySelectorAll('[data-matrix-op]').forEach((input) => {
                    if (input instanceof HTMLInputElement) {
                        input.checked = quick === 'read' && READ_ONLY.includes(input.dataset.matrixOp ?? '');
                    }
                });
                sync(cell);
                root.dispatchEvent(new Event('change', { bubbles: true }));
            });
        });
    });
}
