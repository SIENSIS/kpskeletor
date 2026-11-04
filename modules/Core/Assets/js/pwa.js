/**
 * Registers the service worker (/sw.js, network only: it caches nothing) when the page links the
 * web app manifest (PWA enabled); unregisters it when the PWA is disabled.
 */
import { baseUrl } from 'core/config.js';

export async function initPwa() {
    if (!('serviceWorker' in navigator)) {
        return;
    }

    const enabled = document.querySelector('link[rel="manifest"]') !== null;

    try {
        if (enabled) {
            await navigator.serviceWorker.register(baseUrl('sw.js'), { scope: baseUrl() });
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
