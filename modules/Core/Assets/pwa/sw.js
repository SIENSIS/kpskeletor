/*
 * Minimal service worker of the Core (served at /sw.js so it controls the whole site). It exists
 * only so the site can be installed on a phone's home screen: it caches nothing and never stores
 * a response (pages, data and authenticated content always come from the network).
 */
self.addEventListener('install', () => self.skipWaiting());

self.addEventListener('activate', (event) => event.waitUntil(self.clients.claim()));

// A fetch handler that lets every request go to the network: it never answers from a cache.
self.addEventListener('fetch', () => {});
