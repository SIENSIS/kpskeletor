<?php

declare(strict_types=1);

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Web Push notifications (the `push` channel; docs/notifications.md, D-44-01, D-44-02).
 *
 * The VAPID keys and the contact come from `.env` (`push.vapidPublicKey`, `push.vapidPrivateKey`,
 * `push.subject`), written by `app:install` and `php spark push:keys`. The channel is turned on
 * with the setting `Core.pushEnabled` (Settings › Security › Notifications).
 */
class Push extends BaseConfig
{
    /** Public key: the uncompressed P-256 point, base64url (87 characters). */
    public string $vapidPublicKey = '';

    /** Private key: the P-256 scalar d, base64url (43 characters). Secret: `.env` only. */
    public string $vapidPrivateKey = '';

    /** Contact of the site for the push services: `mailto:…` or `https://…` (RFC 8292). */
    public string $subject = '';

    /**
     * Hosts of the push services a subscription may point at (SSRF protection, D-44-02): exact
     * names or `*.example.com` (subdomains only). Only `https://` endpoints on these hosts are
     * accepted, never IP addresses, and the hosts join the outbound allow-list while push is on.
     * Checked 2026-11-20:
     * - Chrome, Edge (Chromium), Opera, Samsung Internet, Android: `fcm.googleapis.com`;
     * - Firefox: `updates.push.services.mozilla.com`;
     * - Edge on Windows (legacy WNS endpoints): `*.notify.windows.com`;
     * - Safari on macOS, iOS and iPadOS 16.4+: `web.push.apple.com`.
     *
     * @var list<string>
     */
    public array $serviceHosts = [
        'fcm.googleapis.com',
        'updates.push.services.mozilla.com',
        '*.notify.windows.com',
        'web.push.apple.com',
    ];

    /** Seconds a push service keeps an undelivered message (`TTL` header): 1 day. */
    public int $ttl = 86400;

    /** Delivery attempts of a queued message before it is marked failed (429 and 5xx answers). */
    public int $maxAttempts = 5;

    /** A subscription is removed after this many failed deliveries in a row. */
    public int $maxFailures = 10;

    /** Devices one user may subscribe. */
    public int $maxDevices = 20;

    /** Messages sent per run of the scheduled task. */
    public int $batch = 100;

    /** Sent and failed queue rows are deleted after these days. */
    public int $keepDays = 7;
}
