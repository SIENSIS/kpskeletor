/*
 * Service worker of the Core (served at /sw.js so it controls the whole site). It caches nothing
 * and never stores a response (pages, data and authenticated content always come from the
 * network). It lets the site be installed on a phone's home screen (PWA) and shows Web Push
 * notifications (the `push` channel, D-44-02): `push` shows the message, `notificationclick`
 * focuses an open tab of the site or opens the message's URL, on this origin only.
 */
self.addEventListener('install', () => self.skipWaiting());

self.addEventListener('activate', (event) => event.waitUntil(self.clients.claim()));

// A fetch handler that lets every request go to the network: it never answers from a cache.
self.addEventListener('fetch', () => {});

/** A URL of this site, or the site's root (other origins are never opened). */
function sameOriginUrl(value) {
    const root = new URL('./', self.registration.scope).href;

    try {
        const url = new URL(String(value || ''), root);

        return url.origin === self.location.origin ? url.href : root;
    } catch (error) {
        return root;
    }
}

/** A string of at most `max` characters, or ''. */
function text(value, max) {
    return typeof value === 'string' ? value.slice(0, max) : '';
}

self.addEventListener('push', (event) => {
    let data = {};

    try {
        data = event.data ? event.data.json() : {};
    } catch (error) {
        data = {};
    }

    const title = text(data.title, 200) || self.location.hostname;
    const icon = text(data.icon, 500);
    const options = {
        body: text(data.body, 500),
        tag: text(data.tag, 100) || undefined,
        renotify: Boolean(data.tag),
        data: { url: sameOriginUrl(data.url) },
    };

    if (icon !== '') {
        options.icon = sameOriginUrl(icon);
        options.badge = options.icon;
    }

    event.waitUntil(self.registration.showNotification(title, options));
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const target = sameOriginUrl(event.notification.data && event.notification.data.url);

    event.waitUntil((async () => {
        const windows = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });

        for (const client of windows) {
            if (new URL(client.url).origin === self.location.origin && 'focus' in client) {
                await client.focus();

                if ('navigate' in client) {
                    try {
                        await client.navigate(target);
                    } catch (error) {
                        await self.clients.openWindow(target);
                    }
                }

                return;
            }
        }

        await self.clients.openWindow(target);
    })());
});
