/**
 * Icon field (`data-component="core/sections/icon-field"`): the text input keeps the value
 * (`bi-star`, the only thing posted); "Choose" opens a searchable grid of the vendored Bootstrap
 * Icons (names from `data-icons` on the section form). Arrow keys move inside the grid, Enter or
 * a click chooses, Escape closes it. Without JavaScript the name is typed.
 */
import { t } from 'core/config.js';

const LIMIT = 240;

export function mount(field) {
    const input = field.querySelector('[data-icon-input]');
    const toggle = field.querySelector('[data-icon-toggle]');
    const panel = field.querySelector('[data-icon-panel]');
    const search = field.querySelector('[data-icon-search]');
    const options = field.querySelector('[data-icon-options]');
    const count = field.querySelector('[data-icon-count]');
    const preview = field.querySelector('[data-icon-preview]');
    const names = (field.closest('[data-icons]')?.dataset.icons || '').split(',').filter(Boolean);

    if (!input || !toggle || !panel || !options || names.length === 0) {
        return;
    }

    const showPreview = () => {
        const value = input.value.trim();
        preview.className = 'bi ' + (/^bi-[a-z0-9-]+$/.test(value) ? value : 'bi-square');
    };

    const render = () => {
        const query = (search.value || '').trim().toLowerCase().replace(/^bi-?/, '');
        const found = names.filter((name) => query === '' || name.includes(query));
        options.replaceChildren();

        found.slice(0, LIMIT).forEach((name, index) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'btn btn-light kp-icon-option';
            button.dataset.icon = 'bi-' + name;
            button.setAttribute('role', 'option');
            button.setAttribute('aria-selected', input.value === 'bi-' + name ? 'true' : 'false');
            button.setAttribute('aria-label', name);
            button.title = name;
            button.tabIndex = index === 0 ? 0 : -1;
            const icon = document.createElement('i');
            icon.className = 'bi bi-' + name;
            icon.setAttribute('aria-hidden', 'true');
            button.append(icon);
            options.append(button);
        });

        count.textContent = found.length === 0 ? t('sections.iconNone') : t('sections.iconCount', { count: found.length });
    };

    const open = (state) => {
        panel.hidden = !state;
        toggle.setAttribute('aria-expanded', state ? 'true' : 'false');

        if (state) {
            render();
            search.focus();
        }
    };

    const choose = (icon) => {
        input.value = icon;
        input.dispatchEvent(new Event('change', { bubbles: true }));
        showPreview();
        open(false);
        toggle.focus();
        count.textContent = t('sections.iconChosen', { name: icon });
    };

    toggle.hidden = false;
    toggle.addEventListener('click', () => open(panel.hidden));
    search.addEventListener('input', render);
    input.addEventListener('input', showPreview);

    options.addEventListener('click', (event) => {
        const button = event.target.closest('[data-icon]');

        if (button) {
            choose(button.dataset.icon);
        }
    });

    panel.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            event.preventDefault();
            open(false);
            toggle.focus();

            return;
        }

        const buttons = Array.from(options.querySelectorAll('[data-icon]'));
        const current = buttons.indexOf(document.activeElement);

        if (event.key === 'ArrowDown' && document.activeElement === search && buttons.length > 0) {
            event.preventDefault();
            buttons[0].focus();

            return;
        }

        if (current < 0) {
            return;
        }

        const columns = Math.max(1, Math.round(options.clientWidth / (buttons[0].offsetWidth || 40)));
        const moves = { ArrowRight: 1, ArrowLeft: -1, ArrowDown: columns, ArrowUp: -columns };

        if (event.key in moves) {
            event.preventDefault();
            const next = buttons[Math.min(buttons.length - 1, Math.max(0, current + moves[event.key]))];
            buttons.forEach((button) => { button.tabIndex = -1; });
            next.tabIndex = 0;
            next.focus();
        }
    });

    showPreview();
}
