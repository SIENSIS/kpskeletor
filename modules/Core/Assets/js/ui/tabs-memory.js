/**
 * Remembers the active tab of each tabs component ([data-tabs="<id>"]) during the session, per
 * page, and opens the tab named in the URL hash (#<pane id>).
 */
function key(prefix) {
    return 'kp-tab:' + window.location.pathname + ':' + prefix;
}

function show(button) {
    if (button && window.bootstrap && window.bootstrap.Tab) {
        window.bootstrap.Tab.getOrCreateInstance(button).show();
    }
}

export function initTabsMemory(root = document) {
    for (const component of root.querySelectorAll('[data-tabs]')) {
        const prefix = component.dataset.tabs;
        const buttons = [...component.querySelectorAll('[data-bs-toggle="tab"]')];
        const hash = window.location.hash;
        let stored = null;

        try {
            stored = window.sessionStorage.getItem(key(prefix));
        } catch (error) {
            stored = null;
        }

        const wanted = buttons.find((b) => hash !== '' && b.dataset.bsTarget === hash)
            || buttons.find((b) => stored !== null && b.dataset.bsTarget === stored);

        show(wanted);

        for (const button of buttons) {
            button.addEventListener('shown.bs.tab', () => {
                try {
                    window.sessionStorage.setItem(key(prefix), button.dataset.bsTarget || '');
                } catch (error) {
                    // storage disabled: nothing to remember
                }
            });
        }
    }
}
