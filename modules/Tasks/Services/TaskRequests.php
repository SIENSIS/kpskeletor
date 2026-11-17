<?php

declare(strict_types=1);

namespace Modules\Tasks\Services;

use CodeIgniter\I18n\Time;
use Modules\Core\I18n\Locales;
use Modules\Core\Mail\MailQueue;
use Modules\Core\Notifications\RecipientLocale;
use Modules\Tasks\Notifications\TaskRequested;

/**
 * The "request a to-do task" form of the public site (tutorial 12): stores a request, notifies
 * the users who review them (`tasks-requests.list`) and queues a confirmation email to the
 * visitor in their language; in the back office a request is accepted (a private to-do task is
 * created) or rejected.
 */
final class TaskRequests
{
    public const STATUSES = ['new', 'accepted', 'rejected'];

    /** Validation rules of the public form (the captcha is checked separately). */
    public const RULES = [
        'name'    => 'required|max_length[120]',
        'email'   => 'required|valid_email|max_length[190]',
        'title'   => 'required|min_length[3]|max_length[190]',
        'details' => 'permit_empty|max_length[5000]',
    ];

    public function __construct(private readonly MailQueue $mail = new MailQueue())
    {
    }

    /**
     * Stores a validated request, notifies the reviewers and queues the confirmation email.
     *
     * @param array{name: string, email: string, title: string, details: string} $data
     *
     * @return int the request id
     */
    public function submit(array $data, string $locale): int
    {
        $locale = Locales::isAvailable($locale) ? $locale : Locales::default();
        $now    = Time::now()->toDateTimeString();
        $db     = db_connect();

        $db->table('task_requests')->insert([
            'name'       => trim($data['name']),
            'email'      => trim($data['email']),
            'title'      => trim($data['title']),
            'details'    => trim($data['details']) === '' ? null : trim($data['details']),
            'locale'     => $locale,
            'status'     => 'new',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $id = (int) $db->insertID();

        notifyPermission('tasks-requests.list', new TaskRequested(['id' => $id, 'title' => trim($data['title']), 'name' => trim($data['name'])]));
        $this->confirm(trim($data['email']), trim($data['name']), trim($data['title']), $locale);

        activity_log('Tasks', 'request_received', [
            'resource'  => 'tasks-requests',
            'record_id' => $id,
            'summary'   => trim($data['title']),
        ]);

        return $id;
    }

    /**
     * Creates a private to-do task from a new request; null when it was not new.
     *
     * @param array<string, mixed> $request
     */
    public function accept(array $request, int $userId): ?int
    {
        if (($request['status'] ?? '') !== 'new') {
            return null;
        }

        $db  = db_connect();
        $now = Time::now()->toDateTimeString();

        $db->transStart();
        $db->table('tasks')->insert([
            'title'       => (string) $request['title'],
            'description' => trim(((string) ($request['details'] ?? '')) . "\n\n" . lang('Tasks.requestedBy', [(string) $request['name']])),
            'status'      => 'todo',
            'priority'    => 'normal',
            'visibility'  => 'private',
            'created_by'  => $userId > 0 ? $userId : null,
            'created_at'  => $now,
            'updated_at'  => $now,
        ]);
        $taskId = (int) $db->insertID();
        $db->table('task_requests')->where('id', (int) $request['id'])->where('status', 'new')->update(['status' => 'accepted', 'task_id' => $taskId, 'updated_at' => $now]);
        $db->transComplete();

        return $db->transStatus() ? $taskId : null;
    }

    /**
     * @param array<string, mixed> $request
     */
    public function reject(array $request): bool
    {
        if (($request['status'] ?? '') !== 'new') {
            return false;
        }

        db_connect()->table('task_requests')->where('id', (int) $request['id'])->where('status', 'new')
            ->update(['status' => 'rejected', 'updated_at' => Time::now()->toDateTimeString()]);

        return db_connect()->affectedRows() > 0;
    }

    /**
     * Queues the confirmation email in the visitor's language.
     */
    private function confirm(string $email, string $name, string $title, string $locale): void
    {
        [$subject, $body] = RecipientLocale::using($locale, static function () use ($name, $title, $locale): array {
            $subject = lang('Tasks.email.subject', [site_name()]);

            return [$subject, view('Modules\Tasks\Views\emails\request_received', [
                'subject' => $subject,
                'name'    => $name,
                'title'   => $title,
                'site'    => site_name(),
                'locale'  => $locale,
            ], ['debug' => false])];
        });

        $this->mail->push($email, $subject, $body);
    }
}
