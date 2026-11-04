/**
 * Demo component of the theme preview (/theme-preview/components): shows how a component reads
 * its options and a JSON data block, sets a width through the CSSOM (no style="" attribute) and
 * uses the core toast, confirm, modal and http modules under the enforced CSP.
 */
import { jsonData, t } from 'core/config.js';
import { get } from 'core/http.js';
import { confirm } from 'core/ui/confirm.js';
import { openModal } from 'core/ui/modal.js';
import { toast } from 'core/ui/toast.js';

export function mount(element, options) {
    const items = jsonData(options.dataId || '') || [];
    const list = element.querySelector('[data-demo-list]');
    const bar = element.querySelector('[data-demo-progress]');
    const count = element.querySelector('[data-demo-count]');
    let value = Number(options.start) || 0;

    for (const item of items) {
        const li = document.createElement('li');
        li.className = 'list-group-item d-flex justify-content-between align-items-center';
        const name = document.createElement('span');
        name.textContent = item.name;
        const badge = document.createElement('span');
        badge.className = 'badge text-bg-secondary rounded-pill';
        badge.textContent = String(item.count);
        li.append(name, badge);
        list.append(li);
    }

    const render = () => {
        count.textContent = String(value);
        bar.style.width = Math.min(100, value * 10) + '%';
        bar.parentElement.setAttribute('aria-valuenow', String(Math.min(100, value * 10)));
    };

    element.addEventListener('click', async (event) => {
        const button = event.target instanceof Element ? event.target.closest('[data-demo-action]') : null;

        if (button === null) {
            return;
        }

        switch (button.dataset.demoAction) {
        case 'increment':
            value = Math.min(10, value + 1);
            render();
            break;
        case 'toast':
            toast(button.dataset.message || 'OK', { type: 'success' });
            break;
        case 'confirm':
            if (await confirm(button.dataset.message || '', { variant: 'danger' })) {
                value = 0;
                render();
                toast(t('demo.reset'), { type: 'info' });
            }
            break;
        case 'modal':
            await openModal({ title: button.dataset.title || '', body: button.dataset.message || '' });
            break;
        case 'http':
            try {
                const data = await get(options.pingUrl);
                toast(data.message, { type: 'success' });
            } catch (error) {
                // http.js already showed the error toast
            }
            break;
        case 'http-error':
            try {
                await get(options.errorUrl);
            } catch (error) {
                // the JSON error became a toast
            }
            break;
        default:
            break;
        }
    });

    render();
    element.dataset.demoReady = 'true';
}
