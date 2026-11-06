<?php

declare(strict_types=1);

namespace Modules\Core\Notifications;

/**
 * A notification type: a class in a module's `Notifications/` folder declared in the manifest
 * (`$notifications`), sent with `notify()`, `notifyGroup()` or `notifyPermission()`
 * (docs/notifications.md).
 *
 * ```php
 * final class NewNotice extends NotificationType
 * {
 *     public static function title(): string { return lang('News.notifications.newNotice'); }
 *     public static function icon(): string { return 'bi bi-megaphone'; }
 *
 *     public function render(): NotificationMessage
 *     {
 *         return new NotificationMessage(
 *             lang('News.notifications.newNoticeTitle', [$this->data['title'] ?? '']),
 *             url: 'backend/news/notices/' . ($this->data['id'] ?? ''),
 *         );
 *     }
 * }
 *
 * notifyPermission('news-notices.list', new NewNotice(['id' => 7, 'title' => 'Closed on Monday']));
 * ```
 *
 * The data is stored as JSON and the text is rendered when it is shown, in the reader's language:
 * keep only scalar values in it, never secrets. Emails are rendered in the recipient's language
 * when they are queued.
 */
abstract class NotificationType
{
    public const DATABASE = 'database';
    public const EMAIL    = 'email';

    /** Every channel, in display order. */
    public const CHANNELS = [self::DATABASE, self::EMAIL];

    /** Default email view: title, body and a link (`Modules\Core\Views\emails\notification`). */
    public const EMAIL_VIEW = 'Modules\Core\Views\emails\notification';

    /**
     * @param array<string, scalar|null|array<array-key, scalar|null>> $data
     */
    public function __construct(protected array $data = [])
    {
    }

    /**
     * Rebuilds a stored notification without calling the subclass's constructor.
     *
     * @param array<string, mixed> $data
     */
    public static function fromData(array $data): static
    {
        $notification       = (new \ReflectionClass(static::class))->newInstanceWithoutConstructor();
        $notification->data = $data;

        return $notification;
    }

    /** Name of the type in the preferences page (a language string). */
    abstract public static function title(): string;

    /** Optional explanation in the preferences page. */
    public static function description(): string
    {
        return '';
    }

    /** Bootstrap Icons class shown in the bell and the list. */
    public static function icon(): string
    {
        return 'bi bi-bell';
    }

    /**
     * Channels used when the user has not chosen (and always, for mandatory types).
     *
     * @return list<string>
     */
    public static function defaultChannels(): array
    {
        return self::CHANNELS;
    }

    /**
     * Mandatory types are always sent through their default channels; users cannot turn them off.
     */
    public static function mandatory(): bool
    {
        return false;
    }

    /** The text shown in the bell, the list and the email, in the current language. */
    abstract public function render(): NotificationMessage;

    /**
     * @return array<string, mixed>
     */
    public function data(): array
    {
        return $this->data;
    }

    public function emailSubject(): string
    {
        return $this->render()->title;
    }

    /** View of the email body (HTML); it receives `message`, `url`, `subject` and `preferencesUrl`. */
    public function emailView(): string
    {
        return self::EMAIL_VIEW;
    }
}
