/**
 * Tree editor of the Menus module (`data-component="menus/tree-editor"`, modules/Menus/Views/
 * items.php and backend.php): reorder and nest the items of a public menu or the entries of the
 * back-office menu.
 *
 * - The move buttons (up, down, outdent, indent) work in the page instead of submitting the form.
 * - The handle can be dragged (pointer events: mouse, pen, touch): drop on the upper half of a
 *   row to put the item before it, on the lower half after it, and to the right of the row's
 *   start to nest it inside. With the handle focused, the arrow keys move the item (up/down
 *   reorder, right nests under the previous item, left takes it out of its parent).
 * - "Save order" posts the whole tree as JSON in the `tree` field. Without JavaScript the move
 *   buttons submit one move each.
 *
 * Root lists carry `data-tree-root` and `data-zone`; an item never goes inside itself and never
 * deeper than `data-max-depth`.
 */
const NEST_OFFSET = 40;

export function mount(form) {
    const input = form.querySelector('[data-tree-input]');
    const save = form.querySelector('[data-tree-save]');
    const status = form.querySelector('[data-tree-status]');
    const mode = form.dataset.mode || 'public';
    const maxDepth = Number.parseInt(form.dataset.maxDepth || '3', 10);
    const texts = {
        moved: form.dataset.movedText || 'Moved',
    };

    if (!input) {
        return;
    }

    const items = () => Array.from(form.querySelectorAll('[data-tree-item]'));
    const childList = (item) => item.querySelector(':scope > [data-tree-list]');
    const label = (item) => item.querySelector('.menu-tree-label strong')?.textContent?.trim() || '';

    const depthOf = (item) => {
        let depth = 0;

        for (let node = item; node; node = node.parentElement?.closest('[data-tree-item]')) {
            depth += 1;
        }

        return depth;
    };

    const heightOf = (item) => {
        const children = Array.from(childList(item)?.querySelectorAll(':scope > [data-tree-item]') || []);

        return 1 + children.reduce((max, child) => Math.max(max, heightOf(child)), 0);
    };

    const fits = (item, list) => {
        const parentItem = list.closest('[data-tree-item]');
        const depth = parentItem ? depthOf(parentItem) + 1 : 1;

        return depth + heightOf(item) - 1 <= maxDepth && !item.contains(list);
    };

    const serialise = () => {
        const rows = [];

        items().forEach((item) => {
            const list = item.parentElement;
            const parentItem = list.closest('[data-tree-item]');
            const root = item.closest('[data-tree-root]');
            const siblings = Array.from(list.querySelectorAll(':scope > [data-tree-item]'));

            if (mode === 'public') {
                rows.push({
                    id: Number.parseInt(item.dataset.key, 10),
                    parent: parentItem ? Number.parseInt(parentItem.dataset.key, 10) : null,
                    position: (siblings.indexOf(item) + 1) * 10,
                });
            } else {
                rows.push({
                    key: item.dataset.key,
                    parent: parentItem ? parentItem.dataset.key : null,
                    zone: root ? root.dataset.zone : 'sidebar',
                });
            }
        });

        input.value = JSON.stringify(rows);
    };

    const changed = (item) => {
        serialise();
        save?.classList.remove('d-none');
        form.dispatchEvent(new Event('change', { bubbles: true }));

        if (status && item) {
            status.textContent = `${label(item)}: ${texts.moved}`;
        }
    };

    const siblingsOf = (item) => Array.from(item.parentElement.querySelectorAll(':scope > [data-tree-item]'));

    const move = (item, direction) => {
        const siblings = siblingsOf(item);
        const index = siblings.indexOf(item);
        const list = item.parentElement;

        if (direction === 'up' && index > 0) {
            list.insertBefore(item, siblings[index - 1]);
        } else if (direction === 'down' && index < siblings.length - 1) {
            list.insertBefore(siblings[index + 1], item);
        } else if (direction === 'indent' && index > 0) {
            const target = childList(siblings[index - 1]);

            if (!target || !fits(item, target)) {
                return false;
            }

            target.append(item);
        } else if (direction === 'outdent') {
            const parentItem = list.closest('[data-tree-item]');

            if (!parentItem) {
                return false;
            }

            parentItem.after(item);
        } else {
            return false;
        }

        changed(item);

        return true;
    };

    // Buttons and keyboard
    form.addEventListener('click', (event) => {
        const button = event.target.closest('[data-move]');

        if (!button || !form.contains(button)) {
            return;
        }

        event.preventDefault();
        const item = button.closest('[data-tree-item]');

        if (item && move(item, button.dataset.move)) {
            button.focus();
        }
    });

    const keys = { ArrowUp: 'up', ArrowDown: 'down', ArrowLeft: 'outdent', ArrowRight: 'indent' };

    form.addEventListener('keydown', (event) => {
        const handle = event.target.closest('[data-tree-handle]');

        if (!handle || !keys[event.key]) {
            return;
        }

        event.preventDefault();
        const item = handle.closest('[data-tree-item]');

        if (item && move(item, keys[event.key])) {
            handle.focus();
        }
    });

    // Drag and drop (pointer events)
    let marker = null;

    const clearMarker = () => {
        marker?.classList.remove('menu-tree-drop-before', 'menu-tree-drop-after', 'menu-tree-drop-inside');
        marker = null;
    };

    const dropPlace = (item, target, event) => {
        const row = target.querySelector(':scope > .menu-tree-row');
        const box = row.getBoundingClientRect();
        const lower = event.clientY > box.top + box.height / 2;

        if (lower && event.clientX > box.left + NEST_OFFSET && childList(target) && fits(item, childList(target))) {
            return 'inside';
        }

        return lower ? 'after' : 'before';
    };

    form.querySelectorAll('[data-tree-handle]').forEach((handle) => {
        handle.classList.remove('d-none');

        handle.addEventListener('pointerdown', (event) => {
            if (event.pointerType === 'mouse' && event.button !== 0) {
                return;
            }

            event.preventDefault();
            const item = handle.closest('[data-tree-item]');
            item.classList.add('menu-tree-dragging');
            let place = null;
            let target = null;

            const onMove = (moveEvent) => {
                const below = document.elementFromPoint(moveEvent.clientX, moveEvent.clientY);
                const over = below ? below.closest('[data-tree-item]') : null;
                const root = below ? below.closest('[data-tree-root]') : null;
                clearMarker();
                target = null;

                if (over && form.contains(over) && !item.contains(over)) {
                    place = dropPlace(item, over, moveEvent);

                    if (place !== 'inside' && !fits(item, over.parentElement)) {
                        return;
                    }

                    target = over;
                    marker = over;
                    over.classList.add(`menu-tree-drop-${place}`);
                } else if (root && form.contains(root) && !root.querySelector(':scope > [data-tree-item]')) {
                    target = root; // an empty zone
                    place = 'inside';
                    marker = root;
                    root.classList.add('menu-tree-drop-inside');
                }
            };

            const onUp = () => {
                document.removeEventListener('pointermove', onMove);
                document.removeEventListener('pointerup', onUp);
                document.removeEventListener('pointercancel', onUp);
                item.classList.remove('menu-tree-dragging');
                clearMarker();

                if (!target) {
                    return;
                }

                if (target.matches('[data-tree-root]')) {
                    target.append(item);
                } else if (place === 'inside') {
                    childList(target).prepend(item);
                } else if (place === 'before') {
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

    // A submit by a move button without the page script never reaches here; "Save order" posts the tree.
    form.addEventListener('submit', (event) => {
        if (event.submitter && event.submitter.hasAttribute('formaction')) {
            return; // delete / reset buttons
        }

        serialise();
    });
}
