<?php

declare(strict_types=1);

namespace Modules\Auth\Services;

use CodeIgniter\Email\Email;
use CodeIgniter\Shield\Entities\User;
use Throwable;

/**
 * Security emails (magic links, password resets, new-device notices): sent inline with a short
 * timeout and logged (decision D-00-15), never through the queued notification channel.
 * The log names the kind of email and the user id, never the token.
 */
final class SecurityMailer
{
    /** SMTP timeout in seconds for security emails. */
    public const TIMEOUT = 5;

    /**
     * @param array<string, mixed> $data
     */
    public function send(User $user, string $kind, string $subject, string $view, array $data = []): bool
    {
        $to = (string) $user->email;

        if ($to === '') {
            return false;
        }

        try {
            /** @var Email $email */
            $email = service('email');
            $email->initialize(['mailType' => 'html', 'SMTPTimeout' => self::TIMEOUT]);
            $email->setFrom((string) setting('Email.fromEmail'), (string) (setting('Email.fromName') ?? ''));
            $email->setTo($to);
            $email->setSubject($subject);
            $email->setMessage(view($view, $data + ['user' => $user, 'subject' => $subject], ['debug' => false]));

            $sent = $email->send(false);

            if ($sent === false) {
                log_message('error', 'Security email "{kind}" to user {id} failed: {debug}', [
                    'kind'  => $kind,
                    'id'    => $user->id,
                    'debug' => $email->printDebugger(['headers']),
                ]);
            } else {
                log_message('info', 'Security email "{kind}" sent to user {id}.', ['kind' => $kind, 'id' => $user->id]);
            }

            $email->clear(true);

            return $sent;
        } catch (Throwable $e) {
            log_message('error', 'Security email "{kind}" to user {id} failed: {message}', [
                'kind'    => $kind,
                'id'      => $user->id,
                'message' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
