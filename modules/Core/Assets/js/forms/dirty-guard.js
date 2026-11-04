/**
 * Warns before leaving a page with unsaved changes in a form[data-dirty-guard]. Submitting the
 * form (or a reset) clears the warning.
 */
const dirty = new Set();

function onBeforeUnload(event) {
    if (dirty.size > 0) {
        event.preventDefault();
        event.returnValue = '';
    }
}

export function mount(form) {
    if (!(form instanceof HTMLFormElement) || form.dataset.dirtyGuardBound === 'true') {
        return;
    }

    form.dataset.dirtyGuardBound = 'true';
    const mark = () => {
        dirty.add(form);
        form.dataset.dirty = 'true';
    };
    const clear = () => {
        dirty.delete(form);
        delete form.dataset.dirty;
    };

    form.addEventListener('input', mark);
    form.addEventListener('change', mark);
    form.addEventListener('submit', clear);
    form.addEventListener('reset', clear);
}

export function initDirtyGuards(root = document) {
    for (const form of root.querySelectorAll('form[data-dirty-guard]')) {
        mount(form);
    }

    window.addEventListener('beforeunload', onBeforeUnload);
}
