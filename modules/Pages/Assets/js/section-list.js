/**
 * The list of a page's sections (`data-component="pages/section-list"`, Views/editor/list.php):
 *
 * - the up/down buttons move a section in the page instead of submitting the form (without
 *   JavaScript they submit one move each);
 * - the handle can be dragged (pointer events: mouse, pen, touch): drop on the upper half of a
 *   row to put the section before it, on the lower half after it; with the handle focused, the
 *   up and down arrow keys move the section;
 * - "Save order" posts the ids in their new order (`order`, JSON). Every move is announced in a
 *   polite live region and marks the form as changed (the order is saved only on "Save order").
 */
export function mount(form) {
    const list = form.querySelector('[data-section-list]');
    const input = form.querySelector('[data-order-input]');
    const save = form.querySelector('[data-order-save]');
    const status = form.querySelector('[data-section-status]');
    const movedText = form.dataset.movedText || '{label}: {n}';

    if (!list || !input) {
        return;
    }

    const items = () => Array.from(list.querySelectorAll(':scope > [data-section-item]'));

    const serialise = () => {
        input.value = JSON.stringify(items().map((item) => Number.parseInt(item.dataset.id, 10)));
    };

    const refresh = () => {
        const all = items();
        all.forEach((item, index) => {
            const up = item.querySelector('[data-move="up"]');
            const down = item.querySelector('[data-move="down"]');

            if (up) {
                up.disabled = index === 0;
            }

            if (down) {
                down.disabled = index === all.length - 1;
            }
        });
    };

    const changed = (item) => {
        serialise();
        refresh();
        save?.classList.remove('d-none');
        form.dispatchEvent(new Event('change', { bubbles: true }));

        if (status && item) {
            status.textContent = movedText.replace('{label}', item.dataset.label || '').replace('{n}', String(items().indexOf(item) + 1));
        }
    };

    const move = (item, direction) => {
        const all = items();
        const index = all.indexOf(item);

        if (direction === 'up' && index > 0) {
            all[index - 1].before(item);
        } else if (direction === 'down' && index < all.length - 1) {
            all[index + 1].after(item);
        } else {
            return false;
        }

        changed(item);

        return true;
    };

    form.addEventListener('click', (event) => {
        const button = event.target.closest('[data-move]');

        if (!button || !form.contains(button)) {
            return;
        }

        event.preventDefault();
        const item = button.closest('[data-section-item]');

        if (item && move(item, button.dataset.move)) {
            (button.disabled ? item.querySelector('[data-section-handle]') : button)?.focus();
        }
    });

    const keys = { ArrowUp: 'up', ArrowDown: 'down' };

    form.addEventListener('keydown', (event) => {
        const handle = event.target.closest('[data-section-handle]');

        if (!handle || !keys[event.key]) {
            return;
        }

        event.preventDefault();
        const item = handle.closest('[data-section-item]');

        if (item && move(item, keys[event.key])) {
            handle.focus();
        }
    });

    // Drag and drop (pointer events)
    let marker = null;

    const clearMarker = () => {
        marker?.classList.remove('kp-drop-before', 'kp-drop-after');
        marker = null;
    };

    form.querySelectorAll('[data-section-handle]').forEach((handle) => {
        handle.hidden = false;

        handle.addEventListener('pointerdown', (event) => {
            if (event.pointerType === 'mouse' && event.button !== 0) {
                return;
            }

            event.preventDefault();
            const item = handle.closest('[data-section-item]');
            item.classList.add('kp-dragging');
            let target = null;
            let place = 'after';

            const onMove = (moveEvent) => {
                const below = document.elementFromPoint(moveEvent.clientX, moveEvent.clientY);
                const over = below ? below.closest('[data-section-item]') : null;
                clearMarker();
                target = null;

                if (over && list.contains(over) && over !== item) {
                    const box = over.getBoundingClientRect();
                    place = moveEvent.clientY > box.top + box.height / 2 ? 'after' : 'before';
                    target = over;
                    marker = over;
                    over.classList.add(`kp-drop-${place}`);
                }
            };

            const onUp = () => {
                document.removeEventListener('pointermove', onMove);
                document.removeEventListener('pointerup', onUp);
                document.removeEventListener('pointercancel', onUp);
                item.classList.remove('kp-dragging');
                clearMarker();

                if (!target) {
                    return;
                }

                if (place === 'before') {
                    target.before(item);
                } else {
                    target.after(item);
                }

                changed(item);
                handle.focus();
            };

            document.addEventListener('pointermove', onMove);
            document.addEventListener('pointerup', onUp);
            document.addEventListener('pointercancel', onUp);
        });
    });

    form.addEventListener('submit', (event) => {
        if (event.submitter && event.submitter.hasAttribute('formaction')) {
            return; // duplicate, hide and delete buttons
        }

        serialise();
    });

    refresh();
}
