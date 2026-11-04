/*
 * Light/dark/auto colour mode before the first paint (classic script loaded in <head> with the
 * CSP nonce, before the stylesheets). The server already prints data-bs-theme for an explicit
 * choice; for "auto" this script follows prefers-color-scheme and reacts to system changes.
 */
(function () {
    'use strict';

    var root = document.documentElement;
    var media = window.matchMedia ? window.matchMedia('(prefers-color-scheme: dark)') : null;

    function apply() {
        var mode = root.getAttribute('data-theme-mode') || 'auto';

        if (mode === 'auto') {
            root.setAttribute('data-bs-theme', media && media.matches ? 'dark' : 'light');
        } else if (mode === 'light' || mode === 'dark') {
            root.setAttribute('data-bs-theme', mode);
        }
    }

    apply();

    if (media && media.addEventListener) {
        media.addEventListener('change', apply);
    }

    document.addEventListener('kp:theme-mode', apply);
}());
