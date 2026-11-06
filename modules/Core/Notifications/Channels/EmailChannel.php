<?php

declare(strict_types=1);

namespace Modules\Core\Notifications\Channels;

use CodeIgniter\Shield\Entities\User;
use Modules\Core\Mail\MailQueue;
use Modules\Core\Notifications\NotificationType;
use Modules\Core\Notifications\RecipientLocale;

/**
 * The `email` channel: the email is rendered in the recipient's language and queued in
 * `mail_queue`; the scheduled task SendQueuedEmails sends it (never inline in the request,
 * decision D-00-15).
 */
final class EmailChannel
{
    public function __construct(private readonly MailQueue $queue = new MailQueue())
    {
    }

    public function deliver(User $user, NotificationType $notification): bool
    {
        $to = trim((string) $user->email);

        if ($to === '' || filter_var($to, FILTER_VALIDATE_EMAIL) === false) {
            return false;
        }

        [$subject, $body] = RecipientLocale::using(RecipientLocale::for($user), static function () use ($notification, $user): array {
            $message = $notification->render();
            $subject = $notification->emailSubject();
            $path    = $message->path();

            return [$subject, view($notification->emailView(), [
                'subject'        => $subject,
                'message'        => $message,
                'user'           => $user,
                'url'            => $path !== null ? site_url($path) : null,
                'preferencesUrl' => site_url('backend/profile/notifications'),
            ], ['debug' => false])];
        });

        return $this->queue->push($to, $subject, $body, (int) $user->id);
    }
}
