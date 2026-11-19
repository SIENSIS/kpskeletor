/**
 * Show/hide button on every password field (docs/frontend.md, "Password fields").
 *
 * Started by core/app.js for the whole page, and for fields added later (a MutationObserver), so
 * the views of the modules and the forms kpcrudy renders need no change. A field opts out with
 * `data-no-reveal`.
 *
 * - Markup: the input (or its `.form-floating` wrapper) is placed in a Bootstrap `.input-group`
 *   (an existing one is reused) followed by a `type="button"` toggle with `aria-controls`,
 *   `aria-pressed` and an accessible name that changes ("Show the password" / "Hide the
 *   password"). Validation feedback that followed the field moves into the group
 *   (`.has-validation`), so `is-invalid` messages keep showing; help texts stay where they were.
 * - Behaviour: hidden by default; clicking keeps the focus in the field when it was there; the
 *   field goes back to `type="password"` when its form is submitted and on `pagehide`, so the
 *   browser never stores or restores it as plain text. Without JavaScript nothing changes.
 */
import { t } from 'core/config.js';

const SELECTOR = 'input[type="password"]:not([data-no-reveal])';
const fields = new Set();
let counter = 0;
let started = false;

function hide(input) {
    if (input.type !== 'password') {
        input.type = 'password';
    }

    const button = input.passwordRevealButton;

    if (button instanceof HTMLButtonElement) {
        update(button, false);
    }
}

function update(button, shown) {
    button.setAttribute('aria-pressed', shown ? 'true' : 'false');
    button.setAttribute('aria-label', t(shown ? 'password.hide' : 'password.show'));
    button.title = button.getAttribute('aria-label');

    const icon = button.querySelector('i');

    if (icon !== null) {
        icon.className = shown ? 'bi bi-eye-slash' : 'bi bi-eye';
    }
}

/**
 * The element that goes inside the input group: the field, or its floating-label wrapper.
 */
function boxOf(input) {
    const parent = input.parentElement;

    return parent !== null && parent.classList.contains('form-floating') ? parent : input;
}

function groupFor(input, box) {
    const parent = box.parentElement;

    if (parent !== null && parent.classList.contains('input-group')) {
        return parent;
    }

    const group = document.createElement('div');
    group.className = 'input-group password-reveal';
    const hadFocus = document.activeElement === input;
    parent.insertBefore(group, box);
    group.appendChild(box);

    if (hadFocus) {
        input.focus();
    }

    return group;
}

/**
 * Validation messages right after the field (Bootstrap shows them with `.is-invalid ~ …`) move
 * into the group, after the button, which keeps them visible.
 */
function moveFeedback(group, input) {
    let next = group.nextElementSibling;
    const moved = [];

    while (next !== null && (next.classList.contains('invalid-feedback') || next.classList.contains('valid-feedback'))) {
        moved.push(next);
        next = next.nextElementSibling;
    }

    if (moved.length > 0 || input.classList.contains('is-invalid')) {
        group.classList.add('has-validation');
    }

    for (const element of moved) {
        group.appendChild(element);
    }
}

export function enhance(input) {
    if (!(input instanceof HTMLInputElement) || input.passwordRevealButton !== undefined || input.hasAttribute('data-no-reveal') || input.parentElement === null) {
        return;
    }

    if (input.id === '') {
        counter += 1;
        input.id = 'password-reveal-' + counter;
    }

    const box = boxOf(input);
    const group = groupFor(input, box);
    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'btn btn-outline-secondary password-reveal-toggle';
    button.setAttribute('aria-controls', input.id);
    button.dataset.passwordReveal = '';
    button.disabled = input.disabled;
    const icon = document.createElement('i');
    icon.setAttribute('aria-hidden', 'true');
    button.appendChild(icon);
    update(button, false);

    // After the field (and before any other add-on that follows it).
    box.after(button);
    moveFeedback(group, input);

    input.passwordRevealButton = button;
    fields.add(input);

    // Keep the focus in the field when the pointer clicks the button.
    button.addEventListener('pointerdown', (event) => {
        if (document.activeElement === input) {
            event.preventDefault();
        }
    });
    button.addEventListener('click', () => {
        const shown = input.type === 'password';
        const hadFocus = document.activeElement === input;
        input.type = shown ? 'text' : 'password';
        update(button, shown);

        if (hadFocus) {
            input.focus();
        }
    });

    const form = input.form;

    if (form !== null && form.dataset.passwordRevealBound !== 'true') {
        form.dataset.passwordRevealBound = 'true';
        // Capture phase: before any other submit handler reads or serialises the form.
        form.addEventListener('submit', () => {
            for (const field of fields) {
                if (field.form === form) {
                    hide(field);
                }
            }
        }, true);
    }
}

export function initPasswordReveal(root = document) {
    for (const input of root.querySelectorAll(SELECTOR)) {
        enhance(input);
    }

    if (started) {
        return;
    }

    started = true;
    window.addEventListener('pagehide', () => {
        for (const field of fields) {
            hide(field);
        }
    });

    if (typeof MutationObserver === 'function' && document.body !== null) {
        new MutationObserver((records) => {
            for (const record of records) {
                for (const node of record.addedNodes) {
                    if (!(node instanceof Element)) {
                        continue;
                    }

                    if (node.matches(SELECTOR)) {
                        enhance(node);
                    }

                    for (const input of node.querySelectorAll(SELECTOR)) {
                        enhance(input);
                    }
                }
            }
        }).observe(document.body, { childList: true, subtree: true });
    }
}
