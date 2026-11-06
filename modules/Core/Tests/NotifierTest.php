<?php

declare(strict_types=1);

namespace Modules\Core\Tests;

use CodeIgniter\I18n\Time;
use CodeIgniter\Test\Mock\MockEmail;
use Modules\Core\Mail\MailQueue;
use Modules\Core\Notifications\Notifier;
use Modules\Core\Tasks\SendQueuedEmails;
use Tests\Support\Scheduler\DeclaresFixtures;
use Tests\Support\Scheduler\MandatoryNotice;
use Tests\Support\Scheduler\TestNotice;

/**
 * Notifications: routing to users, groups and permission holders; preferences and mandatory
 * types; undeclared and disabled types; the email queue sent by the scheduled task, retries and
 * the recipient's language.
 *
 * @internal
 */
final class NotifierTest extends RbacTestCase
{
    use DeclaresFixtures;

    /** @var list<array<string, mixed>> */
    private array $sent = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->sent = [];
        \CodeIgniter\Events\Events::on('email', function (array $archive): void {
            $this->sent[] = $archive;
        });
        service('language')->setLocale('en');
        $this->declareFixtures([['Core', 'notification', TestNotice::class], ['Core', 'notification', MandatoryNotice::class]]);
    }

    /**
     * @return list<int>
     */
    private function notified(string $type = TestNotice::class): array
    {
        $ids = array_map(intval(...), array_column(db_connect()->table('notifications')->where('type', $type)->get()->getResultArray(), 'user_id'));
        sort($ids);

        return $ids;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function queued(): array
    {
        return db_connect()->table('mail_queue')->orderBy('id')->get()->getResultArray();
    }

    public function testNotifyAUserStoresTheNotificationAndQueuesTheEmail(): void
    {
        $ana = $this->createUser('ana@example.com');

        $this->assertSame(1, notify($ana, new TestNotice(['name' => 'Backup'])));

        $this->assertSame([(int) $ana->id], $this->notified());
        $row = db_connect()->table('notifications')->get()->getRowArray();
        $this->assertIsArray($row);
        $this->assertSame('Core', $row['module']);
        $this->assertNull($row['read_at']);
        $this->assertSame('The scheduled task Backup failed', Notifier::messageOf($row)->title, 'Rendered from the stored data.');

        $mail = $this->queued();
        $this->assertCount(1, $mail);
        $this->assertSame('ana@example.com', $mail[0]['to_email']);
        $this->assertSame('pending', $mail[0]['status']);
        $this->assertStringContainsString('Body of Backup', (string) $mail[0]['body']);
        $this->assertStringContainsString(site_url('backend/somewhere'), (string) $mail[0]['body']);
        $this->assertSame([], $this->sentEmails(), 'Never sent inline.');
    }

    public function testRecipientsByIdAreDeduplicatedAndBannedUsersSkipped(): void
    {
        $ana    = $this->createUser('ana@example.com');
        $banned = $this->createUser('bad@example.com');
        $banned->ban('Spam');

        $this->assertSame(1, notify([(int) $ana->id, $ana, (int) $banned->id, 999], new TestNotice()));
        $this->assertSame([(int) $ana->id], $this->notified());
    }

    public function testNotifyGroupAndNotifyPermission(): void
    {
        $admin  = $this->createUser('boss@example.com', 'admin');
        $editor = $this->createUser('ed@example.com', 'editor');
        $root   = $this->createUser('root@example.com', 'superadmin');
        $this->createUser('ana@example.com', 'user');

        $this->assertSame(2, notifyGroup(['admin', 'editor'], new TestNotice()));
        $this->assertSame([(int) $admin->id, (int) $editor->id], $this->notified());

        db_connect()->table('notifications')->emptyTable();
        $this->assertSame(2, notifyPermission('schedule.run', new TestNotice()));
        $this->assertSame([(int) $admin->id, (int) $root->id], $this->notified(), 'Checked user by user; superadmins hold every permission.');

        db_connect()->table('notifications')->emptyTable();
        $this->assertSame(0, notifyPermission('unknown.permission', new TestNotice()), 'Unknown permissions reach nobody.');
    }

    public function testPreferencesChooseTheChannels(): void
    {
        $ana      = $this->createUser('ana@example.com');
        $notifier = service('notifications');

        $notifier->savePreferences((int) $ana->id, [TestNotice::class => ['database'], MandatoryNotice::class => [], 'Not\\A\\Type' => ['email']]);
        $this->assertSame(['database'], $notifier->channelsFor((int) $ana->id, TestNotice::class));
        $this->assertSame(1, db_connect()->table('notification_preferences')->countAllResults(), 'Mandatory and unknown types are not stored.');

        notify($ana, new TestNotice());
        $this->assertSame([(int) $ana->id], $this->notified());
        $this->assertSame([], $this->queued(), 'Email turned off.');

        $notifier->savePreferences((int) $ana->id, [TestNotice::class => ['email', 'sms']]);
        $this->assertSame(['email'], $notifier->channelsFor((int) $ana->id, TestNotice::class));

        $notifier->savePreferences((int) $ana->id, [TestNotice::class => []]);
        $this->assertSame(0, notify($ana, new TestNotice()), 'Everything turned off.');
    }

    public function testMandatoryTypesIgnorePreferences(): void
    {
        $ana = $this->createUser('ana@example.com');
        db_connect()->table('notification_preferences')->insert(['user_id' => $ana->id, 'type' => MandatoryNotice::class, 'channels' => '', 'updated_at' => Time::now()->toDateTimeString()]);

        $this->assertSame(['database'], service('notifications')->channelsFor((int) $ana->id, MandatoryNotice::class));
        $this->assertSame(1, notify($ana, new MandatoryNotice()));
        $this->assertSame([(int) $ana->id], $this->notified(MandatoryNotice::class));
        $this->assertSame([], $this->queued(), 'Its only default channel is the database.');
    }

    public function testUndeclaredTypesAndTypesOfDisabledModulesAreNotSent(): void
    {
        $ana = $this->createUser('ana@example.com');

        $this->declareFixtures([['News', 'notification', TestNotice::class]]);
        $this->setModuleEnabled('News', false);
        $this->forgetDeclarations();

        $this->assertSame(0, notify($ana, new TestNotice()));
        $this->assertSame(0, notify($ana, new MandatoryNotice()), 'Not declared at all.');
        $this->assertSame(0, db_connect()->table('notifications')->countAllResults());
        $this->assertArrayNotHasKey(TestNotice::class, service('notifications')->types());
    }

    public function testTheScheduledTaskSendsTheQueue(): void
    {
        $ana = $this->createUser('ana@example.com');
        notify($ana, new TestNotice(['name' => 'Report']));

        $result = (new SendQueuedEmails())->run();

        $this->assertFalse($result->failed());
        $this->assertStringContainsString('Sent: 1.', $result->output);
        $this->assertSame('sent', $this->queued()[0]['status']);
        $this->assertCount(1, $this->sentEmails());
        $this->assertSame('ana@example.com', $this->sentEmails()[0]['recipients'][0] ?? null);

        $this->assertStringContainsString('Sent: 0.', (new SendQueuedEmails())->run()->output, 'Not sent twice.');
    }

    public function testAFailedEmailIsRetriedThenGivenUp(): void
    {
        $ana = $this->createUser('ana@example.com');
        notify($ana, new TestNotice());
        $email = service('email');
        $this->assertInstanceOf(MockEmail::class, $email);
        $email->returnValue = false;

        $queue = new MailQueue();
        $now   = Time::now();

        for ($attempt = 1; $attempt < MailQueue::MAX_ATTEMPTS; $attempt++) {
            $this->assertSame(['sent' => 0, 'failed' => 0, 'retry' => 1], $queue->sendPending(50, $now));
            $this->assertSame(['sent' => 0, 'failed' => 0, 'retry' => 0], $queue->sendPending(50, $now), 'Waits before retrying.');
            $now = $now->addHours(2);
        }

        $this->assertSame(['sent' => 0, 'failed' => 1, 'retry' => 0], $queue->sendPending(50, $now));
        $row = $this->queued()[0];
        $this->assertSame('failed', $row['status']);
        $this->assertSame(MailQueue::MAX_ATTEMPTS, (int) $row['attempts']);
        $this->assertNotEmpty($row['last_error']);
    }

    public function testEmailsAreRenderedInTheRecipientsLanguage(): void
    {
        $ana = $this->createUser('ana@example.com');
        db_connect()->table('user_profiles')->insert(['user_id' => $ana->id, 'locale' => 'es']);

        notify($ana, new TestNotice(['name' => 'Copia']));

        $mail = $this->queued()[0];
        $this->assertSame('Ha fallado la tarea programada Copia', $mail['subject']);
        $this->assertSame('en', service('language')->getLocale(), 'The request language is restored.');
    }

    public function testStoredNotificationsOfRemovedTypesStillRender(): void
    {
        $message = Notifier::messageOf(['id' => 1, 'type' => 'Gone\\Type', 'data' => '{}']);

        $this->assertSame(lang('Core.notifications.unavailable'), $message->title);
        $this->assertNull((new \Modules\Core\Notifications\NotificationMessage('x', '', 'https://evil.example/'))->path(), 'Only internal links.');
        $this->assertNull((new \Modules\Core\Notifications\NotificationMessage('x', '', '//evil.example/'))->path());
        $this->assertSame('backend/news', (new \Modules\Core\Notifications\NotificationMessage('x', '', '/backend/news'))->path());
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function sentEmails(): array
    {
        return $this->sent;
    }
}
