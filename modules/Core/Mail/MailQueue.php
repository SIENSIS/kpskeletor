<?php

declare(strict_types=1);

namespace Modules\Core\Mail;

use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Email\Email;
use CodeIgniter\I18n\Time;
use Config\Database;
use Throwable;

/**
 * The outgoing email queue of the notification `email` channel (`mail_queue`). `push()` stores a
 * rendered email; `sendPending()` (the scheduled task SendQueuedEmails) sends a batch through the
 * `email` service (GuardedEmail: the SMTP host must be in the outbound allow-list), retrying a
 * failed email later, up to MAX_ATTEMPTS times. Security emails never go through here.
 */
final class MailQueue
{
    public const MAX_ATTEMPTS = 5;

    /** SMTP timeout in seconds while sending the queue. */
    public const TIMEOUT = 10;

    /** Sent and failed emails are deleted after these days. */
    public const KEEP_DAYS = 30;

    /**
     * @param BaseConnection<object|resource, object|resource>|null $db
     */
    public function __construct(private readonly ?BaseConnection $db = null)
    {
    }

    /**
     * Queues an email. `$replyTo` (optional) is the address answers go to; the sender is always
     * the site's address. It must be a single valid address without line breaks, otherwise the
     * email is not queued.
     */
    public function push(string $to, string $subject, string $body, ?int $userId = null, ?string $replyTo = null): bool
    {
        $now = Time::now()->toDateTimeString();

        if ($replyTo !== null && ! self::validAddress($replyTo)) {
            return false;
        }

        return (bool) $this->db()->table('mail_queue')->insert([
            'user_id'      => $userId,
            'to_email'     => mb_substr($to, 0, 255),
            'reply_to'     => $replyTo,
            'subject'      => mb_substr(str_replace(["\r", "\n"], ' ', $subject), 0, 255),
            'body'         => $body,
            'status'       => 'pending',
            'attempts'     => 0,
            'available_at' => $now,
            'created_at'   => $now,
        ]);
    }

    public function pendingCount(): int
    {
        return $this->db()->table('mail_queue')->where('status', 'pending')->countAllResults();
    }

    /**
     * Sends up to `$limit` pending emails that are due.
     *
     * @return array{sent: int, failed: int, retry: int}
     */
    public function sendPending(int $limit = 50, ?Time $now = null): array
    {
        $now ??= Time::now();
        $result = ['sent' => 0, 'failed' => 0, 'retry' => 0];
        $rows   = $this->db()->table('mail_queue')
            ->where('status', 'pending')
            ->where('available_at <=', $now->toDateTimeString())
            ->orderBy('id')
            ->limit($limit)
            ->get()->getResultArray();

        foreach ($rows as $row) {
            // Claim the row so a parallel run does not send it twice.
            $claimed = $this->db()->table('mail_queue')
                ->where('id', $row['id'])->where('status', 'pending')->where('attempts', $row['attempts'])
                ->update(['attempts' => (int) $row['attempts'] + 1, 'available_at' => $now->addMinutes(15)->toDateTimeString()]);

            if (! $claimed || $this->db()->affectedRows() !== 1) {
                continue;
            }

            $error = $this->deliver($row);

            if ($error === null) {
                $this->db()->table('mail_queue')->where('id', $row['id'])->update(['status' => 'sent', 'sent_at' => $now->toDateTimeString(), 'last_error' => null]);
                $result['sent']++;

                continue;
            }

            $attempts = (int) $row['attempts'] + 1;
            $final    = $attempts >= self::MAX_ATTEMPTS;
            $this->db()->table('mail_queue')->where('id', $row['id'])->update([
                'status'       => $final ? 'failed' : 'pending',
                'last_error'   => mb_substr($error, 0, 255),
                'available_at' => $now->addMinutes(5 * 2 ** ($attempts - 1))->toDateTimeString(),
            ]);
            log_message('error', 'Queued email {id} to user {user} not sent (attempt {attempt}): {error}', [
                'id' => $row['id'], 'user' => $row['user_id'] ?? 0, 'attempt' => $attempts, 'error' => $error,
            ]);
            $final ? $result['failed']++ : $result['retry']++;
        }

        return $result;
    }

    /**
     * Deletes sent and failed emails older than KEEP_DAYS.
     */
    public function prune(?Time $now = null): int
    {
        $limit = ($now ?? Time::now())->subDays(self::KEEP_DAYS)->toDateTimeString();
        $this->db()->table('mail_queue')->whereIn('status', ['sent', 'failed'])->where('created_at <', $limit)->delete();

        return $this->db()->affectedRows();
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return string|null The error, or null when sent
     */
    private function deliver(array $row): ?string
    {
        try {
            /** @var Email $email */
            $email = service('email');
            $email->clear(true);
            $email->initialize(['mailType' => 'html', 'SMTPTimeout' => self::TIMEOUT]);
            $email->setFrom((string) setting('Email.fromEmail'), (string) (setting('Email.fromName') ?? ''));
            $email->setTo((string) $row['to_email']);

            $replyTo = (string) ($row['reply_to'] ?? '');

            if ($replyTo !== '' && self::validAddress($replyTo)) {
                $email->setReplyTo($replyTo);
            }
            $email->setSubject((string) $row['subject']);
            $email->setMessage((string) $row['body']);

            if ($email->send(false)) {
                $email->clear(true);

                return null;
            }

            $email->clear(true);

            return 'The email service refused or failed to send the message.';
        } catch (Throwable $e) {
            return $e->getMessage();
        }
    }

    /**
     * One email address, at most 255 characters, without control characters (no header
     * injection through a `Reply-To`).
     */
    public static function validAddress(string $address): bool
    {
        return $address !== ''
            && strlen($address) <= 255
            && preg_match('/[\x00-\x1F\x7F]/', $address) !== 1
            && filter_var($address, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * @return BaseConnection<object|resource, object|resource>
     */
    private function db(): BaseConnection
    {
        return $this->db ?? Database::connect();
    }
}
