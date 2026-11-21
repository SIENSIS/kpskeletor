/**
 * Section form (`data-component="core/sections/form"`, Modules\Core\Sections\SectionFormView):
 *
 * - language tabs: only the controls of the chosen language (`[data-locale]`) are shown; without
 *   JavaScript every language is shown with its name;
 * - list fields (`[data-list]`): add an item from the `<template>` (`__i__` becomes a new index in
 *   every attribute),
 *   remove it, move it up or down; the add button disappears when the list is full;
 * - link fields: only the control of the chosen kind of target is shown;
 * - the background image picker is shown only for the background "image"; the alternative texts
 *   of an image only when it is not decorative.
 *
 * Every change is announced in a polite live region and marks the form as changed (dirty guard).
 */
import { t } from 'core/config.js';

let counter = 0;

export function mount(root) {
    const form = root.closest('form') || root;
    const tabs = root.querySelector('[data-locale-tabs]');
    let locale = tabs?.querySelector('[data-locale-tab]')?.dataset.localeTab || null;

    const changed = () => form.dispatchEvent(new Event('change', { bubbles: true }));

    const applyLocale = (scope = root) => {
        if (!tabs || locale === null) {
            return;
        }

        scope.querySelectorAll('[data-locale]').forEach((element) => {
            element.hidden = element.dataset.locale !== locale;
        });
    };

    if (tabs) {
        tabs.hidden = false;
        root.classList.add('kp-locale-tabbed');

        const select = (code) => {
            locale = code;
            tabs.querySelectorAll('[data-locale-tab]').forEach((button) => {
                const active = button.dataset.localeTab === code;
                button.classList.toggle('active', active);
                button.setAttribute('aria-pressed', active ? 'true' : 'false');
            });
            applyLocale();
        };

        tabs.addEventListener('click', (event) => {
            const button = event.target.closest('[data-locale-tab]');

            if (button) {
                select(button.dataset.localeTab);
            }
        });

        // A language with an error is shown first.
        const invalid = root.querySelector('[data-locale] .is-invalid');
        select(invalid?.closest('[data-locale]')?.dataset.locale || locale);
    }

    // Links: the control of the chosen kind.
    const syncLink = (field) => {
        const type = field.querySelector('[data-link-type]')?.value || '';

        field.querySelectorAll('[data-link-value]').forEach((element) => {
            element.hidden = element.dataset.linkValue !== type;
        });
        field.querySelectorAll('[data-link-label]').forEach((element) => {
            element.classList.toggle('d-none', type === '');
        });
    };

    // Images: the alternative texts unless decorative.
    const syncImage = (field) => {
        const decorative = field.querySelector('[data-image-decorative]');
        const alts = field.querySelector('[data-image-alts]');

        if (decorative && alts) {
            alts.hidden = decorative.checked;
        }
    };

    const background = root.querySelector('[data-option-background]');
    const backgroundImage = root.querySelector('[data-background-image]');
    const syncBackground = () => {
        if (background && backgroundImage) {
            backgroundImage.hidden = background.value !== 'image';
        }
    };

    root.addEventListener('change', (event) => {
        if (event.target.matches('[data-link-type]')) {
            syncLink(event.target.closest('[data-link-field]'));
        } else if (event.target.matches('[data-image-decorative]')) {
            syncImage(event.target.closest('[data-image-field]'));
        } else if (event.target === background) {
            syncBackground();
        }
    });

    // Lists
    const lists = () => Array.from(root.querySelectorAll('[data-list]'));
    const itemsOf = (list) => Array.from(list.querySelector('[data-list-items]').children).filter((item) => item.matches('[data-list-item]'));

    const syncList = (list) => {
        const items = itemsOf(list);
        const max = Number.parseInt(list.dataset.listMax || '12', 10);
        const add = list.querySelector('[data-list-add]');

        if (add) {
            add.hidden = items.length >= max;
        }

        items.forEach((item, index) => {
            const title = item.querySelector('[data-list-title]');

            if (title) {
                title.textContent = t('sections.item', { n: index + 1 });
            }

            item.querySelectorAll('[data-list-move], [data-list-remove]').forEach((button) => {
                button.hidden = false;
            });
            item.querySelector('[data-list-move="up"]').disabled = index === 0;
            item.querySelector('[data-list-move="down"]').disabled = index === items.length - 1;
        });
    };

    const announce = (list, text) => {
        const status = list.querySelector('[data-list-status]');

        if (status) {
            status.textContent = text;
        }
    };

    root.addEventListener('click', (event) => {
        const add = event.target.closest('[data-list-add]');
        const remove = event.target.closest('[data-list-remove]');
        const move = event.target.closest('[data-list-move]');

        if (add) {
            const list = add.closest('[data-list]');
            const template = list.querySelector(':scope > template[data-list-template]');
            const max = Number.parseInt(list.dataset.listMax || '12', 10);

            if (!template || itemsOf(list).length >= max) {
                announce(list, t('sections.full'));

                return;
            }

            counter += 1;
            const index = 'n' + Date.now().toString(36) + counter;
            // A copy of the template with `__i__` replaced in every attribute (names, ids, labels,
            // component options); no HTML is parsed from strings.
            const fragment = template.content.cloneNode(true);
            fragment.querySelectorAll('*').forEach((element) => {
                for (const attribute of Array.from(element.attributes)) {
                    if (attribute.value.includes('__i__')) {
                        element.setAttribute(attribute.name, attribute.value.replaceAll('__i__', index));
                    }
                }
            });
            const item = fragment.firstElementChild;
            list.querySelector('[data-list-items]').append(item);
            item.querySelectorAll('[data-link-field]').forEach(syncLink);
            item.querySelectorAll('[data-image-field]').forEach(syncImage);
            applyLocale(item);
            syncList(list);
            announce(list, t('sections.added'));
            item.querySelector('input:not([type="hidden"]), select, textarea')?.focus();
            changed();

            return;
        }

        if (remove) {
            const item = remove.closest('[data-list-item]');
            const list = item.closest('[data-list]');
            const items = itemsOf(list);
            const index = items.indexOf(item);
            item.remove();
            syncList(list);
            announce(list, t('sections.removed'));
            const rest = itemsOf(list);
            (rest[Math.min(index, rest.length - 1)]?.querySelector('[data-list-remove]') || list.querySelector('[data-list-add]'))?.focus();
            changed();

            return;
        }

        if (move) {
            const item = move.closest('[data-list-item]');
            const list = item.closest('[data-list]');
            const items = itemsOf(list);
            const index = items.indexOf(item);

            if (move.dataset.listMove === 'up' && index > 0) {
                items[index - 1].before(item);
            } else if (move.dataset.listMove === 'down' && index < items.length - 1) {
                items[index + 1].after(item);
            } else {
                return;
            }

            syncList(list);
            const position = itemsOf(list).indexOf(item) + 1;
            announce(list, t('sections.moved', { n: position }));
            const same = item.querySelector(`[data-list-move="${move.dataset.listMove}"]`);
            (same && !same.disabled ? same : item.querySelector('[data-list-remove]'))?.focus();
            changed();
        }
    });

    root.querySelectorAll('[data-link-field]').forEach(syncLink);
    root.querySelectorAll('[data-image-field]').forEach(syncImage);
    lists().forEach(syncList);
    syncBackground();
}
