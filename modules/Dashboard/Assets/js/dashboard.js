/**
 * The dashboard (`data-component="dashboard/dashboard"`, modules/Dashboard/Views/dashboard.php).
 *
 * "Customise" turns on the edit mode instead of opening the form page: every card gets a drag
 * handle (pointer events: mouse, pen and touch; arrow keys when the handle has the focus), a size
 * menu (the widget's sizes only) and a "hide" button; hidden widgets get a "show" button. Every
 * change is saved at once with `POST backend/dashboard/layout` (JSON); the server keeps only the
 * widgets the user may see. Showing a hidden widget reloads the page so its content is rendered.
 */
import { post } from 'core/http.js';

const DEBOUNCE = 400;

function button(icon, label, extraClass = '') {
    const element = document.createElement('button');
    element.type = 'button';
    element.className = 'btn btn-sm btn-outline-secondary ' + extraClass;
    element.title = label;
    element.setAttribute('aria-label', label);

    const glyph = document.createElement('i');
    glyph.className = 'bi ' + icon;
    glyph.setAttribute('aria-hidden', 'true');
    element.append(glyph);

    return element;
}

function format(text, value) {
    return String(text || '').replace('{0}', String(value));
}

export function mount(root, options) {
    const grid = root.querySelector('[data-dashboard-grid]');
    const toggle = root.querySelector('[data-dashboard-edit]');
    const toggleLabel = root.querySelector('[data-dashboard-edit-label]');
    const help = root.querySelector('[data-dashboard-help]');
    const status = root.querySelector('[data-dashboard-status]');
    const hiddenBox = root.querySelector('[data-dashboard-hidden]');
    const hiddenList = root.querySelector('[data-dashboard-hidden-list]');
    const hiddenHelp = root.querySelector('[data-dashboard-hidden-help]');
    const texts = options.texts || {};
    const sizes = options.sizes || {};
    const columns = options.columns || {};

    if (!grid || !toggle || !options.saveUrl) {
        return;
    }

    const originalLabel = toggleLabel ? toggleLabel.textContent : '';
    let editing = false;
    let timer = null;

    const cards = () => Array.from(grid.querySelectorAll(':scope > [data-widget]'));

    const say = (text) => {
        if (status) {
            status.textContent = text;
        }
    };

    const layout = () => {
        const widgets = cards().map((card) => ({ id: card.dataset.widget, size: card.dataset.size, hidden: false }));

        if (hiddenList) {
            hiddenList.querySelectorAll('[data-hidden-widget]').forEach((item) => {
                widgets.push({ id: item.dataset.hiddenWidget, size: item.dataset.size || null, hidden: true });
            });
        }

        return widgets;
    };

    const save = (reload = false) => {
        window.clearTimeout(timer);
        timer = window.setTimeout(async () => {
            try {
                await post(options.saveUrl, { json: { widgets: layout() } });
                say(texts.saved || '');

                if (reload) {
                    window.location.reload();
                }
            } catch (error) {
                // http.js already showed the error as a toast
            }
        }, reload ? 0 : DEBOUNCE);
    };

    const setSize = (card, size) => {
        card.dataset.size = size;
        card.className = (columns[size] || columns.medium || 'col-12') + ' dashboard-widget' + (editing ? ' dashboard-widget-editing' : '');
    };

    const move = (card, offset) => {
        const list = cards();
        const index = list.indexOf(card);
        const target = list[index + offset];

        if (!target) {
            return;
        }

        if (offset < 0) {
            grid.insertBefore(card, target);
        } else {
            grid.insertBefore(card, target.nextSibling);
        }

        say(format(texts.moved, cards().indexOf(card) + 1));
        save();
    };

    const showButton = (item) => {
        const show = button('bi-eye', texts.show || 'Show', 'ms-1 dashboard-tool');
        show.addEventListener('click', () => {
            item.remove();
            save(true);
        });
        item.append(show);
    };

    const hide = (card) => {
        if (!hiddenList || !hiddenBox) {
            return;
        }

        const title = card.querySelector('.card-header h2');
        const item = document.createElement('li');
        item.className = 'list-inline-item mb-2';
        item.dataset.hiddenWidget = card.dataset.widget || '';
        item.dataset.size = card.dataset.size || '';

        const badge = document.createElement('span');
        badge.className = 'badge text-bg-light border fw-normal py-2';
        badge.textContent = title ? title.textContent.trim() : item.dataset.hiddenWidget;
        item.append(badge);
        showButton(item);

        hiddenList.append(item);
        hiddenBox.classList.remove('d-none');
        card.remove();
        save();
    };

    /** Pointer drag on the handle: the card follows the pointer's position in the grid. */
    const drag = (card, handle) => {
        handle.addEventListener('pointerdown', (event) => {
            if (!editing || (event.pointerType === 'mouse' && event.button !== 0)) {
                return;
            }

            event.preventDefault();
            card.classList.add('dashboard-widget-dragging');
            const start = cards().indexOf(card);

            const onMove = (moveEvent) => {
                card.style.pointerEvents = 'none';
                const below = document.elementFromPoint(moveEvent.clientX, moveEvent.clientY);
                card.style.pointerEvents = '';
                const target = below ? below.closest('[data-widget]') : null;

                if (!target || target === card || target.parentElement !== grid) {
                    return;
                }

                const list = cards();
                const box = target.getBoundingClientRect();
                const after = list.indexOf(target) > list.indexOf(card)
                    || (moveEvent.clientY > box.top + box.height / 2 && moveEvent.clientX > box.left + box.width / 2);
                grid.insertBefore(card, after ? target.nextSibling : target);
            };

            // Listened on the document: moving the card in the DOM drops any pointer capture.
            const onEnd = () => {
                document.removeEventListener('pointermove', onMove);
                document.removeEventListener('pointerup', onEnd);
                document.removeEventListener('pointercancel', onEnd);
                card.classList.remove('dashboard-widget-dragging');

                if (cards().indexOf(card) !== start) {
                    say(format(texts.moved, cards().indexOf(card) + 1));
                    save();
                }
            };

            document.addEventListener('pointermove', onMove);
            document.addEventListener('pointerup', onEnd);
            document.addEventListener('pointercancel', onEnd);
        });

        handle.addEventListener('keydown', (event) => {
            if (['ArrowUp', 'ArrowLeft'].includes(event.key)) {
                event.preventDefault();
                move(card, -1);
                handle.focus();
            } else if (['ArrowDown', 'ArrowRight'].includes(event.key)) {
                event.preventDefault();
                move(card, 1);
                handle.focus();
            }
        });
    };

    const addTools = (card) => {
        const tools = card.querySelector('[data-widget-tools]');

        if (!tools) {
            return;
        }

        const handle = button('bi-grip-vertical', texts.move || 'Move', 'dashboard-handle dashboard-tool');
        drag(card, handle);

        const allowed = (card.dataset.sizes || '').split(',').filter((size) => size !== '');
        const tool = [handle];

        if (allowed.length > 1) {
            const select = document.createElement('select');
            select.className = 'form-select form-select-sm dashboard-size dashboard-tool';
            select.setAttribute('aria-label', texts.size || 'Size');
            allowed.forEach((size) => {
                const option = document.createElement('option');
                option.value = size;
                option.textContent = sizes[size] || size;
                option.selected = size === card.dataset.size;
                select.append(option);
            });
            select.addEventListener('change', () => {
                setSize(card, select.value);
                save();
            });
            tool.push(select);
        }

        const hideButton = button('bi-eye-slash', texts.hide || 'Hide', 'dashboard-tool');
        hideButton.addEventListener('click', () => hide(card));
        tool.push(hideButton);

        tools.append(...tool);
    };

    const setEditing = (on) => {
        editing = on;
        root.classList.toggle('dashboard-editing', on);
        toggle.setAttribute('aria-pressed', on ? 'true' : 'false');

        if (toggleLabel) {
            toggleLabel.textContent = on ? (toggleLabel.dataset.done || originalLabel) : originalLabel;
        }

        if (help) {
            help.classList.toggle('d-none', !on);
        }

        if (hiddenHelp) {
            hiddenHelp.classList.toggle('d-none', on);
        }

        root.querySelectorAll('.dashboard-tool').forEach((element) => element.remove());

        if (on) {
            cards().forEach((card) => {
                card.classList.add('dashboard-widget-editing');
                addTools(card);
            });

            if (hiddenList) {
                hiddenList.querySelectorAll('[data-hidden-widget]').forEach(showButton);
            }
        } else {
            cards().forEach((card) => card.classList.remove('dashboard-widget-editing'));
            say('');
        }
    };

    toggle.addEventListener('click', (event) => {
        event.preventDefault();
        setEditing(!editing);
    });
}
