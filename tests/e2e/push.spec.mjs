// Web Push (session 44, D-44-02): Profile › Notifications with push on — axe, overflow and CSP in
// every project; then, in one project, the whole flow in the browser: the permission (granted to
// the context), "Activate notifications on this device", the device listed as "This device",
// "Send a test" queued by the server, the service worker showing a push message delivered through
// the DevTools protocol (the stand-in for the push service), and "Remove".
//
// Real push services cannot be reached from a test run (and Chromium has no Push API in incognito
// contexts), so `PushManager.subscribe()` is replaced in the page by a subscription with real
// P-256 keys on an FCM endpoint: the server validates and stores it exactly like a browser's. The
// encryption and the delivery of the scheduled task are covered by PHPUnit (PushDeliveryTest).
//
// Needs `Core.pushEnabled` on and the VAPID keys in `.env` on the demo site (docs/development.md);
// the tests are skipped while the site has push off.
// E2E_PUSH_DB=writable/demo.db (the demo site's SQLite file) delivers the payload the server
// queued for "Send a test"; without it a payload of the same shape is used.
import { createECDH, randomBytes } from 'node:crypto';
import { execFileSync } from 'node:child_process';
import { test, expect, checkPage, backend } from './support.mjs';

const FLOW_PROJECT = 'desktop-light';

function base64url(buffer) {
    return Buffer.from(buffer).toString('base64').replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
}

function queuedPayload() {
    const db = process.env.E2E_PUSH_DB;

    if (!db) {
        return { title: 'Notificació de prova', body: 'Les notificacions funcionen en aquest dispositiu.', url: backend('profile/notifications'), tag: 'core-push-test' };
    }

    const script = '$d=new SQLite3($argv[1]);echo $d->querySingle("select payload from push_queue order by id desc limit 1");';

    return JSON.parse(execFileSync('php', ['-r', script, db], { encoding: 'utf8' }));
}

/** Skips the test when the demo site has push off (no device section on the page). */
async function skipWithoutPush(page) {
    await page.goto(backend('profile/notifications'), { waitUntil: 'networkidle' });
    test.skip(await page.locator('[data-component="core/push"]').count() === 0, 'Core.pushEnabled is off on the demo site.');
}

async function fakePushManager(page) {
    const ecdh = createECDH('prime256v1');
    ecdh.generateKeys();
    const fake = {
        endpoint: 'https://fcm.googleapis.com/fcm/send/e2e-' + randomBytes(8).toString('hex'),
        p256dh: base64url(ecdh.getPublicKey()),
        auth: base64url(randomBytes(16)),
    };

    await page.addInitScript((data) => {
        const KEY = 'kp.e2e.subscription';
        const make = (stored) => ({
            endpoint: stored.endpoint,
            options: { applicationServerKey: Uint8Array.from(stored.serverKey).buffer, userVisibleOnly: true },
            toJSON: () => ({ endpoint: stored.endpoint, expirationTime: null, keys: { p256dh: stored.p256dh, auth: stored.auth } }),
            unsubscribe: async () => { localStorage.removeItem(KEY); return true; },
        });

        PushManager.prototype.getSubscription = async function getSubscription() {
            const stored = localStorage.getItem(KEY);
            return stored ? make(JSON.parse(stored)) : null;
        };
        PushManager.prototype.subscribe = async function subscribe(options) {
            const stored = { ...data, serverKey: Array.from(new Uint8Array(options.applicationServerKey)) };
            localStorage.setItem(KEY, JSON.stringify(stored));
            return make(stored);
        };
    }, fake);

    return fake;
}

// The full Chromium (new headless mode) for this file: the headless shell has no notifications.
test.use({ channel: 'chromium' });

test.describe('Web Push', () => {
    test('Profile › Notifications with push on', async ({ page }) => {
        await skipWithoutPush(page);
        await checkPage(page, backend('profile/notifications'), 'push-devices', {
            ready: async (p) => {
                await expect(p.locator('[data-component="core/push"] [data-push-status]')).not.toHaveText('');
            },
        });
        await expect(page.locator('th', { hasText: 'Als meus dispositius' }).first()).toBeVisible();
    });

});

test.describe('Web Push in the browser', () => {
    test('activate, test, receive and remove a device', async ({ page, context, browserName }, testInfo) => {
        test.skip(testInfo.project.name !== FLOW_PROJECT || browserName !== 'chromium', 'The flow runs once, in Chromium.');

        await skipWithoutPush(page);
        await context.grantPermissions(['notifications'], { origin: new URL(testInfo.project.use.baseURL ?? 'http://localhost:8089').origin });
        await fakePushManager(page);

        await page.goto(backend('profile/notifications'), { waitUntil: 'networkidle' });
        const section = page.locator('[data-component="core/push"]');
        await section.getByRole('button', { name: 'Activa les notificacions en aquest dispositiu' }).click();

        await expect(section.locator('[data-push-status]')).toHaveText('Aquest dispositiu rep les vostres notificacions.');
        const device = section.locator('[data-push-devices] li').filter({ hasText: 'Aquest dispositiu' });
        await expect(device).toHaveCount(1);
        await page.screenshot({ path: `writable/e2e/screenshots/${testInfo.project.name}/push-activated.png` });

        await device.getByRole('button', { name: /^Envia una prova/ }).click();
        await expect(page.locator('.toast', { hasText: 'arribarà en menys d\'un minut' })).toBeVisible();

        // The push service's delivery, straight to the service worker.
        const cdp = await context.newCDPSession(page);
        const registrations = [];
        cdp.on('ServiceWorker.workerRegistrationUpdated', (event) => registrations.push(...event.registrations));
        await cdp.send('ServiceWorker.enable');
        await expect.poll(() => registrations.find((r) => r.scopeURL.endsWith('/') && !r.isDeleted)?.registrationId ?? null).not.toBeNull();
        const registration = registrations.find((r) => r.scopeURL.endsWith('/') && !r.isDeleted);
        const payload = queuedPayload();
        await cdp.send('ServiceWorker.deliverPushMessage', {
            origin: new URL(page.url()).origin,
            registrationId: registration.registrationId,
            data: JSON.stringify(payload),
        });

        await expect.poll(async () => page.evaluate(async () => {
            const worker = await navigator.serviceWorker.ready;
            return (await worker.getNotifications()).map((n) => [n.title, n.body, n.data && n.data.url]);
        })).toEqual([[payload.title, payload.body, new URL(payload.url, page.url()).href]]);

        // A URL of another origin is never opened: the worker keeps the site's root.
        await cdp.send('ServiceWorker.deliverPushMessage', {
            origin: new URL(page.url()).origin,
            registrationId: registration.registrationId,
            data: JSON.stringify({ title: 'Other', body: '', url: 'https://evil.example/', tag: 'x' }),
        });
        await expect.poll(async () => page.evaluate(async () => {
            const worker = await navigator.serviceWorker.ready;
            return (await worker.getNotifications({ tag: 'x' })).map((n) => n.data.url);
        })).toEqual([new URL('/', page.url()).href]);

        await device.getByRole('button', { name: /^Elimina/ }).click();
        await page.locator('.modal.show').getByRole('button', { name: 'Confirma' }).click();
        await expect(section.locator('[data-push-devices] li').filter({ hasText: 'Aquest dispositiu' })).toHaveCount(0);
        await expect(section.locator('[data-push-status]')).toHaveText('Aquest dispositiu no rep les vostres notificacions.');
    });
});
