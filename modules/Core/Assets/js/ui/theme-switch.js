/**
 * Light / dark / auto switch ([data-theme-switch] with buttons [data-theme-mode-value]). The
 * choice is applied at once and stored in the kp_theme cookie, so the server prints the right
 * data-bs-theme on the next page (theme-mode.js handles "auto").
 */
import { setCookie } from 'core/cookies.js';

const MODES = ['light', 'dark', 'auto'];
const ICONS = { light: 'bi-sun', dark: 'bi-moon-stars', auto: 'bi-circle-half' };

function refresh(switcher, mode) {
    for (const button of switcher.querySelectorAll('[data-theme-mode-value]')) {
        const active = button.dataset.themeModeValue === mode;
        button.classList.toggle('active', active);
        button.setAttribute('aria-pressed', active ? 'true' : 'false');
    }

    const icon = switcher.querySelector('[data-theme-switch-icon]');

    if (icon !== null) {
        icon.classList.remove(...Object.values(ICONS));
        icon.classList.add(ICONS[mode]);
    }
}

export function setMode(mode) {
    if (!MODES.includes(mode)) {
        return;
    }

    document.documentElement.setAttribute('data-theme-mode', mode);
    document.dispatchEvent(new CustomEvent('kp:theme-mode', { detail: { mode } }));
    setCookie('kp_theme', mode);

    for (const switcher of document.querySelectorAll('[data-theme-switch]')) {
        refresh(switcher, mode);
    }
}

export function initThemeSwitch(root = document) {
    const mode = document.documentElement.getAttribute('data-theme-mode') || 'auto';

    for (const switcher of root.querySelectorAll('[data-theme-switch]')) {
        refresh(switcher, mode);
        switcher.addEventListener('click', (event) => {
            const button = event.target instanceof Element ? event.target.closest('[data-theme-mode-value]') : null;

            if (button !== null) {
                setMode(button.dataset.themeModeValue);
            }
        });
    }
}
