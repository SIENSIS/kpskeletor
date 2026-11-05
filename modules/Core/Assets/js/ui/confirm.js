/**
 * Confirmations with the theme's modal.
 *
 *   if (await confirm('Delete this post?', { variant: 'danger' })) { … }
 *
 * Markup: <a href="…" data-confirm="Delete this post?">, <button data-confirm="…"> inside a form,
 * or <form data-confirm="…">. Optional data-confirm-title, data-confirm-ok, data-confirm-variant.
 * The server still checks every action: this only avoids mistakes.
 */
import { t } from 'core/config.js';
import { openModal } from 'core/ui/modal.js';

const VARIANTS = ['primary', 'danger', 'warning', 'success', 'secondary'];

export async function confirm(message, settings = {}) {
    const variant = VARIANTS.includes(settings.variant) ? settings.variant : 'primary';
    const value = await openModal({
        title: settings.title || t('confirm.title'),
        body: message,
        buttons: [
            { label: settings.cancelLabel || t('confirm.cancel'), value: false, variant: 'secondary' },
            { label: settings.okLabel || t('confirm.ok'), value: true, variant, autofocus: true },
        ],
    });

    return value === true;
}

function settingsOf(element) {
    return {
        title: element.dataset.confirmTitle,
        okLabel: element.dataset.confirmOk,
        variant: element.dataset.confirmVariant || 'danger',
    };
}

export function bindConfirmations(root = document) {
    root.addEventListener('click', async (event) => {
        const trigger = event.target instanceof Element ? event.target.closest('a[data-confirm], button[data-confirm]') : null;

        if (trigger === null || trigger.dataset.confirmed === 'true') {
            return;
        }

        event.preventDefault();

        if (!(await confirm(trigger.dataset.confirm, settingsOf(trigger)))) {
            return;
        }

        if (trigger instanceof HTMLAnchorElement) {
            window.location.assign(trigger.href);

            return;
        }

        trigger.dataset.confirmed = 'true';

        if (trigger.form && trigger.type === 'submit') {
            trigger.form.dataset.confirmed = 'true';
            trigger.form.requestSubmit(trigger);
        } else {
            trigger.click();
        }

        delete trigger.dataset.confirmed;
    });

    root.addEventListener('submit', async (event) => {
        const form = event.target;

        if (!(form instanceof HTMLFormElement) || !form.dataset.confirm || form.dataset.confirmed === 'true') {
            delete form.dataset.confirmed;

            return;
        }

        event.preventDefault();
        const submitter = event.submitter || null;

        if (await confirm(form.dataset.confirm, settingsOf(form))) {
            form.dataset.confirmed = 'true';
            form.requestSubmit(submitter);
        }
    });
}

/**
 * kpcrudy's confirmations (delete, bulk actions, operations with `confirm`) use the theme's
 * modal: kpcrudy dispatches the cancelable `kpcrudy:confirm` event before window.confirm(); this
 * cancels it, asks with confirm() and calls detail.proceed() when accepted.
 */
export function bindKpcrudyConfirmations(target = document) {
    target.addEventListener('kpcrudy:confirm', (event) => {
        const detail = event.detail || {};

        if (typeof detail.proceed !== 'function') {
            return;
        }

        event.preventDefault();
        confirm(String(detail.message || ''), { variant: 'danger' }).then((accepted) => {
            if (accepted) {
                detail.proceed();
            }
        });
    });
}
