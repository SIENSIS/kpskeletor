/**
 * The board of to-do tasks (`data-component="tasks/board"`, modules/Tasks/Views/board/index.php;
 * tutorial 3).
 *
 * The page works without JavaScript: every card the user may move has a small form (status +
 * "Move"). This component:
 * - sends those forms as JSON (`POST backend/tasks/board/{id}/move`) instead of reloading;
 * - adds drag and drop between the columns (cards with `draggable="true"`);
 * - after each move redraws the columns from `options.dataUrl` (the server decides what the user
 *   sees and may move) and announces the result in a live region.
 * Cards are built with DOM methods (never innerHTML with data).
 */
import { get, post } from 'core/http.js';

function element(tag, className, text) {
    const node = document.createElement(tag);

    if (className) {
        node.className = className;
    }

    if (text !== undefined && text !== null) {
        node.textContent = String(text);
    }

    return node;
}

function icon(name) {
    const glyph = element('i', 'bi ' + name);
    glyph.setAttribute('aria-hidden', 'true');

    return glyph;
}

function badgeClass(priority) {
    return { high: 'text-bg-danger', normal: 'text-bg-secondary', low: 'text-bg-light border' }[priority] || 'text-bg-secondary';
}

/** The markup of `Views/board/card.php`, built with DOM methods. */
function buildCard(card, status, texts) {
    const item = element('li', 'card shadow-sm tasks-board-card' + (card.overdue ? ' is-overdue' : ''));
    item.dataset.boardCard = card.id;
    item.dataset.moveUrl = card.moveUrl;

    if (card.canMove) {
        item.draggable = true;
    }

    const body = element('div', 'card-body p-2');
    const head = element('div', 'd-flex justify-content-between gap-2');
    const title = element('h3', 'h6 mb-1 text-break');

    if (card.url) {
        const link = element('a', '', card.title);
        link.href = card.url;
        title.append(link);
    } else {
        title.textContent = card.title;
    }

    const priority = element('span', 'flex-shrink-0 align-self-start');
    priority.append(element('span', 'badge ' + badgeClass(card.priority), (texts.priorities || {})[card.priority] || card.priority));
    head.append(title, priority);
    body.append(head);

    const meta = element('div', 'small text-body-secondary d-flex flex-wrap gap-2');

    if (card.project) {
        const span = element('span');
        span.append(icon('bi-folder2'), ' ', card.project);
        meta.append(span);
    }

    if (card.due) {
        const span = element('span', card.overdue ? 'text-danger fw-semibold' : '');
        span.append(icon('bi-calendar-event'), ' ', card.due);
        meta.append(span);
    }

    if (card.assignees && card.assignees.length > 0) {
        const span = element('span');
        span.append(icon('bi-people'), ' ', card.assignees.join(', '));
        meta.append(span);
    }

    body.append(meta);

    if (card.labels && card.labels.length > 0) {
        const labels = element('div', 'd-flex flex-wrap gap-1 mt-1');
        card.labels.forEach((label) => labels.append(element('span', 'badge rounded-pill text-bg-light border', label)));
        body.append(labels);
    }

    if (card.canMove) {
        const form = element('form', 'd-flex gap-1 mt-2');
        form.method = 'post';
        form.action = card.moveUrl;
        form.dataset.boardMove = '';

        const label = element('label', 'visually-hidden', texts.moveTo);
        label.htmlFor = 'move-' + card.id;

        const select = element('select', 'form-select form-select-sm');
        select.id = 'move-' + card.id;
        select.name = 'status';

        Object.entries(texts.statuses || {}).forEach(([value, text]) => {
            const option = element('option', '', text);
            option.value = value;
            option.selected = value === status;
            select.append(option);
        });

        const button = element('button', 'btn btn-sm btn-outline-primary', texts.move || texts.moveTo);
        button.type = 'submit';

        form.append(label, select, button);
        body.append(form);
    }

    item.append(body);

    return item;
}

export function mount(root, options) {
    const texts = options.texts || {};
    const status = root.querySelector('[data-board-status]');
    const help = root.querySelector('[data-board-help]');
    let dragged = null;

    if (help) {
        help.classList.remove('d-none');
    }

    // The module's stylesheet (drop targets, dragged card), loaded only on this page.
    if (options.css && !document.querySelector('link[data-tasks-css]')) {
        const link = element('link');
        link.rel = 'stylesheet';
        link.href = options.css;
        link.dataset.tasksCss = '';
        document.head.append(link);
    }

    const say = (text) => {
        if (status) {
            status.textContent = text || '';
        }
    };

    const redraw = (columns) => {
        root.querySelectorAll('[data-board-column]').forEach((list) => {
            const key = list.dataset.boardColumn;
            const cards = Array.isArray(columns[key]) ? columns[key] : [];
            list.replaceChildren();

            cards.forEach((card) => list.append(buildCard(card, key, texts)));

            if (cards.length === 0) {
                const empty = element('li', 'small text-body-secondary tasks-board-empty', texts.empty);
                empty.dataset.boardEmpty = '';
                list.append(empty);
            }

            const count = list.closest('.tasks-board-column')?.querySelector('[data-board-count]');

            if (count) {
                count.textContent = String(cards.length);
            }
        });
    };

    const move = async (url, target) => {
        try {
            const result = await post(url, { json: { status: target } });
            say(result && result.message ? result.message : texts.moved);
        } catch (error) {
            // core/http.js already showed the error as a toast.
        }

        if (options.dataUrl) {
            try {
                const data = await get(options.dataUrl, { silent: true });

                if (data && data.columns) {
                    redraw(data.columns);
                }
            } catch (error) {
                window.location.reload();
            }
        }
    };

    root.addEventListener('submit', (event) => {
        const form = event.target;

        if (!(form instanceof HTMLFormElement) || !form.hasAttribute('data-board-move')) {
            return;
        }

        event.preventDefault();
        const select = form.querySelector('select[name="status"]');
        move(form.action, select ? select.value : '');
    });

    root.addEventListener('dragstart', (event) => {
        const card = event.target instanceof Element ? event.target.closest('[data-board-card][draggable="true"]') : null;

        if (!card) {
            return;
        }

        dragged = card;
        card.classList.add('is-dragging');
        event.dataTransfer.effectAllowed = 'move';
        event.dataTransfer.setData('text/plain', card.dataset.boardCard);
    });

    root.addEventListener('dragend', () => {
        if (dragged) {
            dragged.classList.remove('is-dragging');
        }

        dragged = null;
        root.querySelectorAll('.is-drop-target').forEach((list) => list.classList.remove('is-drop-target'));
    });

    root.addEventListener('dragover', (event) => {
        const list = event.target instanceof Element ? event.target.closest('[data-board-column]') : null;

        if (list && dragged) {
            event.preventDefault();
            list.classList.add('is-drop-target');
        }
    });

    root.addEventListener('dragleave', (event) => {
        const list = event.target instanceof Element ? event.target.closest('[data-board-column]') : null;

        if (list && !list.contains(event.relatedTarget)) {
            list.classList.remove('is-drop-target');
        }
    });

    root.addEventListener('drop', (event) => {
        const list = event.target instanceof Element ? event.target.closest('[data-board-column]') : null;

        if (!list || !dragged) {
            return;
        }

        event.preventDefault();
        list.classList.remove('is-drop-target');

        if (dragged.closest('[data-board-column]') !== list) {
            move(dragged.dataset.moveUrl, list.dataset.boardColumn);
        }
    });
}
