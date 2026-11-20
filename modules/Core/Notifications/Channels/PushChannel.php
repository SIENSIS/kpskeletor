<?php

declare(strict_types=1);

namespace Modules\Core\Notifications\Channels;

use CodeIgniter\Shield\Entities\User;
use Modules\Core\Notifications\NotificationMessage;
use Modules\Core\Notifications\NotificationType;
use Modules\Core\Notifications\RecipientLocale;
use Modules\Core\Push\PushQueue;
use Modules\Core\Push\PushSubscriptions;
use Modules\Core\Push\WebPush;
use Throwable;

/**
 * The `push` channel (D-44-02): the message is rendered in the recipient's language and queued
 * in `push_queue` once per device of the user; the scheduled task SendQueuedPush encrypts and
 * sends it (never inline in the request). Nothing is queued while the channel is off or the
 * user has no device. With `Core.pushContent = generic` the payload only says that there is a
 * new notification (a lock screen shows it). The payload never holds secrets or tokens: a title,
 * a short text, an internal URL, an icon and a tag.
 */
final class PushChannel
{
    /** Longest text sent (the system notification shows a few lines). */
    public const BODY_LENGTH = 300;

    public function __construct(
        private readonly PushSubscriptions $subscriptions = new PushSubscriptions(),
        private readonly PushQueue $queue = new PushQueue(),
    ) {
    }

    public function deliver(User $user, NotificationType $notification): bool
    {
        if (! WebPush::enabled()) {
            return false;
        }

        $devices = $this->subscriptions->rowsFor((int) $user->id);

        if ($devices === []) {
            return false;
        }

        $payload = RecipientLocale::using(RecipientLocale::for($user), static fn (): array => self::payload(
            $notification->render(),
            self::tagOf($notification::class),
        ));
        $urgency = $notification::mandatory() ? PushQueue::URGENCY_HIGH : PushQueue::URGENCY_NORMAL;
        $queued  = false;

        foreach ($devices as $device) {
            $queued = $this->queue->push((int) $device['id'], $payload, $urgency) || $queued;
        }

        return $queued;
    }

    /**
     * Queues a test message for one device of the user ("Send a test").
     */
    public function test(User $user, int $deviceId): bool
    {
        if (! WebPush::enabled() || $this->subscriptions->find((int) $user->id, $deviceId) === null) {
            return false;
        }

        $payload = RecipientLocale::using(RecipientLocale::for($user), static fn (): array => self::payload(
            new NotificationMessage(lang('Push.test.title'), lang('Push.test.body'), backend_link('profile/notifications')),
            'core-push-test',
            false,
        ));

        return $this->queue->push($deviceId, $payload, PushQueue::URGENCY_HIGH);
    }

    /**
     * What the service worker shows.
     *
     * @return array{title: string, body: string, url: string, icon: string, tag: string}
     */
    public static function payload(NotificationMessage $message, string $tag, bool $private = true): array
    {
        $path = $message->path();

        if ($private && WebPush::content() === WebPush::CONTENT_GENERIC) {
            return [
                'title' => site_name(),
                'body'  => lang('Push.generic.body'),
                'url'   => backend_url('notifications'),
                'icon'  => self::icon(),
                'tag'   => 'core-notification',
            ];
        }

        return [
            'title' => mb_substr($message->title, 0, 200),
            'body'  => mb_substr(trim(strip_tags($message->body)), 0, self::BODY_LENGTH),
            'url'   => $path !== null ? site_url($path) : backend_url('notifications'),
            'icon'  => self::icon(),
            'tag'   => $tag,
        ];
    }

    /**
     * One tag per type: a newer notification of the same type replaces the older one on screen.
     *
     * @param class-string $class
     */
    public static function tagOf(string $class): string
    {
        return strtolower(trim((string) preg_replace('/[^A-Za-z0-9]+/', '-', $class), '-'));
    }

    private static function icon(): string
    {
        try {
            return pwa_icon_url(192);
        } catch (Throwable) {
            return '';
        }
    }
}
