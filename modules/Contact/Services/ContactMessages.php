<?php

declare(strict_types=1);

namespace Modules\Contact\Services;

use CodeIgniter\I18n\Time;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Models\UserModel;
use Modules\Contact\Notifications\MessageReceived;
use Modules\Core\I18n\Locales;
use Modules\Core\Mail\MailQueue;
use Modules\Core\Notifications\RecipientLocale;
use Modules\Core\Permissions\Authorization;

/**
 * The messages of the contact form: stores a validated message and lets its recipient know.
 *
 * - The members of the recipient's group who may read messages (`contact-messages.show`) get the
 *   notification `MessageReceived` (bell, email or push per their preferences, queued).
 * - The recipient's addresses get an email queued in the site's default language, **from the
 *   site's address** with the visitor only in `Reply-To` (validated, no line breaks); the visitor
 *   never receives anything (no auto-reply: the form cannot be used to send mail to others).
 * - A recipient with neither a group nor addresses: the holders of `contact-messages.all` are
 *   notified, so no message is lost.
 */
final class ContactMessages
{
    public const STATUSES = ['new', 'answered', 'archived'];

    public function __construct(
        private readonly Recipients $recipients = new Recipients(),
        private readonly MailQueue $mail = new MailQueue(),
    ) {
    }

    /**
     * @param array{name: string, email: string, subject: string, message: string} $data
     *
     * @return int the message id
     */
    public function submit(array $data, int $recipientId, string $locale): int
    {
        $locale    = Locales::isAvailable($locale) ? $locale : Locales::default();
        $recipient = $this->recipients->find($recipientId);
        $name      = $this->recipients->names(Locales::default())[$recipientId] ?? '';
        $now       = Time::now()->toDateTimeString();
        $db        = db_connect();

        $db->table('contact_messages')->insert([
            'recipient_id'   => $recipientId,
            'recipient_name' => mb_substr($name, 0, 120),
            'name'           => $data['name'],
            'email'          => $data['email'],
            'subject'        => $data['subject'],
            'message'        => $data['message'],
            'locale'         => $locale,
            'status'         => 'new',
            'created_at'     => $now,
            'updated_at'     => $now,
        ]);
        $id = (int) $db->insertID();

        $notification = new MessageReceived(['id' => $id, 'subject' => $data['subject'], 'name' => $data['name'], 'recipient' => $name]);
        $notified     = 0;

        if ($recipient !== null && $recipient['group_name'] !== null) {
            $notified = notify($this->readers($recipient['group_name']), $notification);
        }

        $emails = $recipient['emails'] ?? [];

        foreach ($emails as $email) {
            $this->email($email, $id, $data, $name);
        }

        if ($notified === 0 && $emails === []) {
            notifyPermission('contact-messages.all', $notification);
        }

        activity_log('Contact', 'message_received', [
            'resource'  => 'contact-messages',
            'record_id' => $id,
            'summary'   => $name,
        ]);

        return $id;
    }

    /**
     * Marks a message answered (or archived); false when it already was.
     *
     * @param 'answered'|'archived' $status
     */
    public function mark(int $id, string $status, ?int $userId = null): bool
    {
        $now     = Time::now()->toDateTimeString();
        $changes = ['status' => $status, 'updated_at' => $now];

        if ($status === 'answered') {
            $changes += ['answered_at' => $now, 'answered_by' => $userId];
        }

        db_connect()->table('contact_messages')->where('id', $id)->where('status !=', $status)->update($changes);

        return db_connect()->affectedRows() > 0;
    }

    /**
     * Deletes the messages older than `$days` days.
     */
    public function purge(int $days, ?Time $now = null): int
    {
        $limit = ($now ?? Time::now())->subDays(max(30, $days))->toDateTimeString();
        db_connect()->table('contact_messages')->where('created_at <', $limit)->delete();

        return db_connect()->affectedRows();
    }

    /**
     * The members of a group who may read the messages.
     *
     * @return list<User>
     */
    private function readers(string $group): array
    {
        $ids   = array_map('intval', array_column(db_connect()->table('auth_groups_users')->select('user_id')->where('group', $group)->get()->getResultArray(), 'user_id'));
        $users = [];

        foreach ($ids === [] ? [] : model(UserModel::class)->whereIn('id', $ids)->findAll() as $user) {
            if (Authorization::for($user)->can('contact-messages.show')) {
                $users[] = $user;
            }
        }

        return $users;
    }

    /**
     * Queues the email to one of the recipient's addresses.
     *
     * @param array{name: string, email: string, subject: string, message: string} $data
     */
    private function email(string $to, int $id, array $data, string $recipient): void
    {
        [$subject, $body] = RecipientLocale::using(Locales::default(), static function () use ($id, $data, $recipient): array {
            $subject = lang('Contact.email.subject', [site_name(), $data['subject']]);

            return [$subject, view('Modules\Contact\Views\emails\message', [
                'subject'   => $subject,
                'data'      => $data,
                'recipient' => $recipient,
                'site'      => site_name(),
                'url'       => backend_url('contact/messages/show/' . \Kpcrudy\Crud::maskId($id, 'contact_messages')),
            ], ['debug' => false])];
        });

        $this->mail->push($to, $subject, $body, null, MailQueue::validAddress($data['email']) ? $data['email'] : null);
    }
}
