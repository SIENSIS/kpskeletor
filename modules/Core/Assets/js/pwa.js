/**
 * Registers the service worker (/sw.js, network only: it caches nothing) when the page links the
 * web app manifest (PWA enabled) or Web Push is on (`push` in the page configuration: the worker
 * shows the notifications); unregisters it when both are off.
 */
import { baseUrl, config } from 'core/config.js';

export function serviceWorkerWanted() {
    return document.querySelector('link[rel="manifest"]') !== null || config().push === true;
}

export async function registerServiceWorker() {
    return navigator.serviceWorker.register(baseUrl('sw.js'), { scope: baseUrl() });
}

export async function initPwa() {
    if (!('serviceWorker' in navigator)) {
        return;
    }

    try {
        if (serviceWorkerWanted()) {
            await registerServiceWorker();
        } else {
            for (const registration of await navigator.serviceWorker.getRegistrations()) {
                if (registration.active && registration.active.scriptURL === baseUrl('sw.js')) {
                    await registration.unregister();
                }
            }
        }
    } catch (error) {
        console.warn('Service worker not available', error);
    }
}
