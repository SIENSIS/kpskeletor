<?php

declare(strict_types=1);

namespace Modules\Core\Push;

use Config\Push;
use Throwable;

/**
 * State of the `push` channel: the setting `Core.pushEnabled` (Settings › Security ›
 * Notifications, off by default), the VAPID keys of `.env` and the payload privacy
 * (`Core.pushContent`: `full` or `generic`). docs/notifications.md, D-44-02.
 */
final class WebPush
{
    public const CONTENT_FULL    = 'full';
    public const CONTENT_GENERIC = 'generic';

    /** @var array<string, bool> keys checked in this process */
    private static array $checked = [];

    /**
     * The channel works: switched on and with valid keys.
     */
    public static function enabled(?Push $config = null): bool
    {
        return self::switchedOn() && self::keysConfigured($config);
    }

    /** The setting `Core.pushEnabled`. */
    public static function switchedOn(): bool
    {
        try {
            return setting('Core.pushEnabled') === true;
        } catch (Throwable) {
            return false; // settings not installed yet
        }
    }

    /**
     * Whether `.env` holds a valid, matching VAPID key pair and a contact.
     */
    public static function keysConfigured(?Push $config = null): bool
    {
        $config ??= config(Push::class);
        $id = hash('sha256', $config->vapidPublicKey . '|' . $config->vapidPrivateKey . '|' . $config->subject);

        return self::$checked[$id] ??= self::problem($config) === null;
    }

    /**
     * What is wrong with the keys (a code: missing, invalid, mismatch, subject), or null.
     */
    public static function problem(?Push $config = null): ?string
    {
        $config ??= config(Push::class);

        if (trim($config->vapidPublicKey) === '' || trim($config->vapidPrivateKey) === '') {
            return 'missing';
        }

        $public  = Base64Url::decode($config->vapidPublicKey);
        $private = Base64Url::decode($config->vapidPrivateKey);

        if ($public === null || $private === null || strlen($private) !== 32 || ! EcKey::isPublicKey($public)) {
            return 'invalid';
        }

        try {
            $token = (new Vapid($public, $private, 'mailto:check@example.invalid'))->token('https://example.invalid', time() + 60);

            if (! Vapid::verify($token, $public)) {
                return 'mismatch';
            }
        } catch (Throwable) {
            return 'invalid';
        }

        return self::validSubject($config->subject) ? null : 'subject';
    }

    public static function validSubject(string $subject): bool
    {
        $subject = trim($subject);

        if (str_starts_with($subject, 'mailto:')) {
            return filter_var(substr($subject, 7), FILTER_VALIDATE_EMAIL) !== false;
        }

        return str_starts_with($subject, 'https://') && filter_var($subject, FILTER_VALIDATE_URL) !== false;
    }

    public static function vapid(?Push $config = null): Vapid
    {
        $config ??= config(Push::class);

        return new Vapid((string) Base64Url::decode($config->vapidPublicKey), (string) Base64Url::decode($config->vapidPrivateKey), trim($config->subject));
    }

    /**
     * The `.env` values of a new key pair.
     *
     * @return array{'push.vapidPublicKey': string, 'push.vapidPrivateKey': string}
     */
    public static function newKeys(): array
    {
        $keys = EcKey::generate();

        return [
            'push.vapidPublicKey'  => Base64Url::encode($keys['public']),
            'push.vapidPrivateKey' => Base64Url::encode($keys['private']),
        ];
    }

    /**
     * The contact written by the installer: the first superadmin's email, else the site URL.
     */
    public static function defaultSubject(string $email, string $baseUrl): string
    {
        if (filter_var($email, FILTER_VALIDATE_EMAIL) !== false) {
            return 'mailto:' . $email;
        }

        return str_starts_with($baseUrl, 'https://') ? rtrim($baseUrl, '/') : 'mailto:webmaster@example.com';
    }

    /** `full` (title and text) or `generic` ("You have a new notification"). */
    public static function content(): string
    {
        try {
            return setting('Core.pushContent') === self::CONTENT_GENERIC ? self::CONTENT_GENERIC : self::CONTENT_FULL;
        } catch (Throwable) {
            return self::CONTENT_FULL;
        }
    }

    /**
     * The push service hosts that join the outbound allow-list (only while the channel is on).
     *
     * @return list<string>
     */
    public static function outboundHosts(): array
    {
        return self::switchedOn() ? config(Push::class)->serviceHosts : [];
    }

    /** Forgets the keys checked in this process (tests, `push:keys`). */
    public static function reset(): void
    {
        self::$checked = [];
    }
}
