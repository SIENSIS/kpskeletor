/**
 * Editor of a `links` widget option (`data-component="dashboard/links-editor"`,
 * modules/Dashboard/Views/links_editor.php): one ordered list of menu entries and free links.
 * Adds a drag handle (pointer events: mouse, pen, touch; arrow keys when focused), up/down/remove
 * buttons and "add"; hides the position fields and renumbers them from the list order, and shows
 * the label/address fields only for free links. Without JavaScript the form works with the
 * position fields and the empty rows.
 */
function iconButton(icon, label) {
    const element = document.createElement('button');
    element.type = 'button';
    element.className = 'btn btn-sm btn-outline-secondary';
    element.title = label;
    element.setAttribute('aria-label', label);

    const glyph = document.createElement('i');
    glyph.className = 'bi ' + icon;
    glyph.setAttribute('aria-hidden', 'true');
    element.append(glyph);

    return element;
}

export function mount(root, options) {
    const list = root.querySelector('[data-links-list]');
    const add = root.querySelector('[data-links-add]');
    const max = Number.parseInt(root.dataset.max || '20', 10);

    if (!list) {
        return;
    }

    const rows = () => Array.from(list.querySelectorAll(':scope > [data-links-row]'));

    const changed = () => {
        rows().forEach((row, index) => {
            const position = row.querySelector('[data-links-position] input');

            if (position) {
                position.value = String((index + 1) * 10);
            }
        });
        list.closest('form')?.dispatchEvent(new Event('change', { bubbles: true }));
    };

    const toggleFree = (row) => {
        const ref = row.querySelector('[data-links-ref]');
        const free = ref instanceof HTMLSelectElement && ref.value === 'url';
        row.querySelectorAll('[data-links-free]').forEach((field) => field.classList.toggle('d-none', !free));
    };

    const move = (row, offset) => {
        const all = rows();
        const target = all[all.indexOf(row) + offset];

        if (!target) {
            return;
        }

        list.insertBefore(row, offset < 0 ? target : target.nextSibling);
        changed();
    };

    const drag = (row, handle) => {
        handle.addEventListener('pointerdown', (event) => {
            if (event.pointerType === 'mouse' && event.button !== 0) {
                return;
            }

            event.preventDefault();
            row.classList.add('dashboard-widget-dragging');

            const onMove = (moveEvent) => {
                row.style.pointerEvents = 'none';
                const below = document.elementFromPoint(moveEvent.clientX, moveEvent.clientY);
                row.style.pointerEvents = '';
                const target = below ? below.closest('[data-links-row]') : null;

                if (!target || target === row || target.parentElement !== list) {
                    return;
                }

                const all = rows();
                list.insertBefore(row, all.indexOf(target) > all.indexOf(row) ? target.nextSibling : target);
            };

            const onEnd = () => {
                document.removeEventListener('pointermove', onMove);
                document.removeEventListener('pointerup', onEnd);
                document.removeEventListener('pointercancel', onEnd);
                row.classList.remove('dashboard-widget-dragging');
                changed();
            };

            document.addEventListener('pointermove', onMove);
            document.addEventListener('pointerup', onEnd);
            document.addEventListener('pointercancel', onEnd);
        });

        handle.addEventListener('keydown', (event) => {
            if (['ArrowUp', 'ArrowLeft'].includes(event.key)) {
                event.preventDefault();
                move(row, -1);
                handle.focus();
            } else if (['ArrowDown', 'ArrowRight'].includes(event.key)) {
                event.preventDefault();
                move(row, 1);
                handle.focus();
            }
        });
    };

    const clear = (row) => {
        row.querySelectorAll('input:not([data-links-position] input)').forEach((input) => { input.value = ''; });
        const ref = row.querySelector('[data-links-ref]');

        if (ref instanceof HTMLSelectElement) {
            ref.value = '';
        }

        toggleFree(row);
    };

    const enhance = (row) => {
        const tools = row.querySelector('[data-links-tools]');
        const handle = iconButton('bi-grip-vertical', options.move || 'Move');
        handle.classList.add('dashboard-handle');
        drag(row, handle);

        const up = iconButton('bi-arrow-up', options.up || 'Up');
        up.addEventListener('click', () => move(row, -1));
        const down = iconButton('bi-arrow-down', options.down || 'Down');
        down.addEventListener('click', () => move(row, 1));
        const remove = iconButton('bi-trash', options.remove || 'Remove');
        remove.addEventListener('click', () => {
            if (rows().length > 1) {
                row.remove();
            } else {
                clear(row);
            }

            changed();
            update();
        });

        tools?.replaceChildren(handle, up, down, remove);
        row.querySelector('[data-links-position]')?.classList.add('d-none');
        row.querySelector('[data-links-ref]')?.addEventListener('change', () => toggleFree(row));
        toggleFree(row);
    };

    const update = () => {
        if (add) {
            add.disabled = rows().length >= max;
        }
    };

    // Keep one empty row as the template for "add"; drop the other empty rows.
    const empty = rows().filter((row) => row.querySelector('[data-links-ref]')?.value === '');
    const template = (empty[0] || rows()[rows().length - 1]).cloneNode(true);
    empty.slice(rows().length - empty.length === 0 ? 1 : 0).forEach((row) => row.remove());
    let next = 1000;

    rows().forEach(enhance);

    if (add) {
        add.classList.remove('d-none');
        add.addEventListener('click', () => {
            const row = template.cloneNode(true);
            const index = next++;

            row.querySelectorAll('[name]').forEach((field) => {
                field.name = field.name.replace(/\[\d+\](\[[a-z]+\])$/, '[' + index + ']$1');
            });
            row.querySelectorAll('[id]').forEach((field) => { field.id = field.id.replace(/-\d+-/, '-' + index + '-'); });
            row.querySelectorAll('label[for]').forEach((label) => { label.htmlFor = label.htmlFor.replace(/-\d+-/, '-' + index + '-'); });
            clear(row);
            list.append(row);
            enhance(row);
            changed();
            update();
            row.querySelector('[data-links-ref]')?.focus();
        });
    }

    changed();
    update();
}
