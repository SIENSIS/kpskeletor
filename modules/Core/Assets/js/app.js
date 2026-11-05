/**
 * Entry point of every page (loaded as an ES module by the layouts): starts the core UI
 * behaviours and the components found on the page ([data-component], see components.js).
 */
import { mountAll, observe } from 'core/components.js';
import { initDirtyGuards } from 'core/forms/dirty-guard.js';
import { initPwa } from 'core/pwa.js';
import { bindConfirmations, bindKpcrudyConfirmations } from 'core/ui/confirm.js';
import { initCookieNotice } from 'core/ui/cookie-notice.js';
import { initSidebar } from 'core/ui/sidebar.js';
import { initTabsMemory } from 'core/ui/tabs-memory.js';
import { initThemeSwitch } from 'core/ui/theme-switch.js';

const steps = [
    () => initThemeSwitch(document),
    () => initSidebar(document),
    () => initTabsMemory(document),
    () => initCookieNotice(document),
    () => initDirtyGuards(document),
    () => bindConfirmations(document),
    () => bindKpcrudyConfirmations(document),
    () => mountAll(document),
    () => observe(document.body),
    () => initPwa(),
];

for (const step of steps) {
    try {
        step();
    } catch (error) {
        console.warn('Start-up step failed', error);
    }
}

document.documentElement.dataset.app = 'ready';
