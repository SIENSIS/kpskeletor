<?php

declare(strict_types=1);

namespace Modules\Core\Tests;

use CodeIgniter\I18n\Time;
use Modules\Core\Http\OutboundAllowList;
use Modules\Core\Http\OutboundException;
use Modules\Core\Notifications\NotificationType;
use Modules\Core\Push\Base64Url;
use Modules\Core\Push\PayloadEncryption;
use Modules\Core\Push\PushQueue;
use Modules\Core\Push\PushSubscriptions;
use Modules\Core\Push\Vapid;
use Modules\Core\Push\WebPush;
use Modules\Core\Tasks\SendQueuedPush;
use Tests\Support\Scheduler\DeclaresFixtures;
use Tests\Support\Scheduler\MandatoryNotice;
use Tests\Support\Scheduler\TestNotice;

/**
 * The `push` channel (D-44-02): one queued message per device, never sent inline; the scheduled
 * task sends through the real OutboundClient (fake transport) with the Web Push headers and a
 * body the browser decrypts; 404/410 remove the device, 429/5xx retry, other errors count
 * failures; preferences, mandatory types, payload privacy, push switched off, deleted users.
 *
 * @internal
 */
final class PushDeliveryTest extends RbacTestCase
{
    use DeclaresFixtures;
    use PushFixturesTrait;

    private PushSubscriptions $devices;

    protected function setUp(): void
    {
        parent::setUp();
        service('language')->setLocale('en');
        $this->declareFixtures([['Core', 'notification', TestNotice::class], ['Core', 'notification', MandatoryNotice::class]]);
        $this->enablePush();
        $this->devices = new PushSubscriptions();
    }

    protected function tearDown(): void
    {
        $this->disablePushConfig();
        parent::tearDown();
    }

    /**
     * @return array{id: int, endpoint: string}
     */
    private function device(int $userId, string $endpoint = ''): array
    {
        $subscription = $this->browserSubscription($endpoint);
        $result       = $this->devices->subscribe($userId, $subscription['endpoint'], $subscription['keys']['p256dh'], $subscription['keys']['auth'], 'Firefox · Linux');
        $this->assertArrayHasKey('id', $result);

        return ['id' => $result['id'], 'endpoint' => $subscription['endpoint']];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function queue(): array
    {
        return db_connect()->table('push_queue')->orderBy('id')->get()->getResultArray();
    }

    /**
     * @param array{method: string, url: string, headers: array<string, string>, body: string|null, ip: string} $request
     *
     * @return array<string, mixed>
     */
    private function decrypt(array $request): array
    {
        $keys = $this->browserKeys[$request['url']];

        return json_decode(PayloadEncryption::decrypt((string) $request['body'], $keys['public'], $keys['private'], $keys['auth']), true, 512, JSON_THROW_ON_ERROR);
    }

    public function testANotificationIsQueuedOncePerDeviceAndNeverSentInline(): void
    {
        $ana       = $this->createUser('ana@example.com');
        $transport = new FakePushTransport();
        $phone     = $this->device((int) $ana->id);
        $laptop    = $this->device((int) $ana->id, 'https://updates.push.services.mozilla.com/wpush/v2/abc');

        $this->assertContains(NotificationType::PUSH, service('notifications')->channelsFor((int) $ana->id, TestNotice::class), 'Push is a default channel (opt-in per device).');
        $this->assertSame(1, notify($ana, new TestNotice(['name' => 'Backup', 'url' => 'backend/somewhere'])));

        $queue = $this->queue();
        $this->assertCount(2, $queue);
        $this->assertSame([$phone['id'], $laptop['id']], array_map(intval(...), array_column($queue, 'subscription_id')));
        $this->assertSame('pending', $queue[0]['status']);
        $this->assertSame('normal', $queue[0]['urgency']);
        $payload = json_decode((string) $queue[0]['payload'], true);
        $this->assertSame('The scheduled task Backup failed', $payload['title']);
        $this->assertSame('Body of Backup', $payload['body']);
        $this->assertSame(site_url('backend/somewhere'), $payload['url']);
        $this->assertSame('tests-support-scheduler-testnotice', $payload['tag']);
        $this->assertCount(0, $transport->sent, 'Nothing is sent inside the request.');

        $result = $this->pushQueue($transport)->sendPending();

        $this->assertSame(['sent' => 2, 'retry' => 0, 'failed' => 0, 'removed' => 0], $result);
        $this->assertCount(2, $transport->sent);

        foreach ($transport->sent as $index => $request) {
            $this->assertSame('POST', $request['method']);
            $this->assertSame([$phone['endpoint'], $laptop['endpoint']][$index], $request['url']);
            $this->assertSame('142.250.200.10', $request['ip'], 'The checked address is pinned by OutboundClient.');
            $this->assertSame('aes128gcm', $request['headers']['Content-Encoding']);
            $this->assertSame('application/octet-stream', $request['headers']['Content-Type']);
            $this->assertSame((string) config('Push')->ttl, $request['headers']['TTL']);
            $this->assertSame('normal', $request['headers']['Urgency']);
            $this->assertMatchesRegularExpression('/^vapid t=([^,]+), k=' . preg_quote(config('Push')->vapidPublicKey, '/') . '$/', $request['headers']['Authorization']);
            preg_match('/t=([^,]+)/', $request['headers']['Authorization'], $token);
            $this->assertTrue(Vapid::verify($token[1], (string) Base64Url::decode(config('Push')->vapidPublicKey)));
            $claims = json_decode((string) Base64Url::decode(explode('.', $token[1])[1]), true);
            $this->assertSame(Vapid::audience($request['url']), $claims['aud']);
            $this->assertSame('The scheduled task Backup failed', $this->decrypt($request)['title'], 'The browser decrypts it with its keys.');
        }

        $this->assertSame(['sent', 'sent'], array_column($this->queue(), 'status'));
        $this->assertNotNull(db_connect()->table('push_subscriptions')->where('id', $phone['id'])->get()->getRowArray()['last_used_at']);
    }

    public function testGoneDevicesAreRemovedAndServerErrorsRetried(): void
    {
        $ana       = $this->createUser('ana@example.com');
        $gone      = $this->device((int) $ana->id);
        $busy      = $this->device((int) $ana->id);
        $transport = new FakePushTransport();
        $transport->answers = [410, 503];

        notify($ana, new TestNotice());
        $now    = Time::now();
        $result = $this->pushQueue($transport)->sendPending(null, $now);

        $this->assertSame(['sent' => 0, 'retry' => 1, 'failed' => 0, 'removed' => 1], $result);
        $this->assertNull($this->devices->find((int) $ana->id, $gone['id']), '410: the device is deleted.');
        $this->assertCount(1, $this->queue(), 'With its queued messages.');
        $row = $this->queue()[0];
        $this->assertSame('pending', $row['status']);
        $this->assertSame('HTTP 503', $row['last_error']);
        $this->assertSame(0, $this->pushQueue($transport)->sendPending(null, $now)['retry'], 'Not due yet: backoff.');

        $transport->answers = [201];
        $this->assertSame(1, $this->pushQueue($transport)->sendPending(null, $now->addMinutes(2))['sent']);
        $this->assertNotNull($this->devices->find((int) $ana->id, $busy['id']));
    }

    public function testOtherErrorsCountFailuresAndTooManyRemoveTheDevice(): void
    {
        $ana       = $this->createUser('ana@example.com');
        $device    = $this->device((int) $ana->id);
        $transport = new FakePushTransport();
        $transport->answers = [400];

        notify($ana, new TestNotice());
        $this->assertSame(1, $this->pushQueue($transport)->sendPending()['failed']);
        $this->assertSame('failed', $this->queue()[0]['status']);
        $this->assertSame(1, (int) db_connect()->table('push_subscriptions')->where('id', $device['id'])->get()->getRowArray()['failures']);

        db_connect()->table('push_subscriptions')->where('id', $device['id'])->update(['failures' => config('Push')->maxFailures - 1]);
        notify($ana, new TestNotice());
        $transport->answers = [403];
        $this->assertSame(1, $this->pushQueue($transport)->sendPending()['removed']);
        $this->assertNull($this->devices->find((int) $ana->id, $device['id']));

        // A transport failure is retried; a refused address is not.
        $other = $this->device((int) $ana->id);
        notify($ana, new TestNotice());
        $transport->answers = [new OutboundException(OutboundException::REASON_TRANSPORT, 'timeout')];
        $this->assertSame(1, $this->pushQueue($transport)->sendPending()['retry']);
        $this->assertNotNull($this->devices->find((int) $ana->id, $other['id']));
    }

    public function testAPrivateAddressOrAnEndpointChangedInTheDatabaseIsNeverCalled(): void
    {
        $ana    = $this->createUser('ana@example.com');
        $device = $this->device((int) $ana->id);
        notify($ana, new TestNotice());

        // A tampered row cannot make the server call another host.
        db_connect()->table('push_subscriptions')->where('id', $device['id'])->update(['endpoint' => 'https://169.254.169.254/latest/meta-data']);
        $transport = new FakePushTransport();
        $this->assertSame(1, $this->pushQueue($transport)->sendPending()['failed']);
        $this->assertSame([], $transport->sent);

        // A push host that resolves to a private address is refused by OutboundClient.
        db_connect()->table('push_subscriptions')->where('id', $device['id'])->update(['endpoint' => $device['endpoint']]);
        notify($ana, new TestNotice());
        $client = new \Modules\Core\Http\OutboundClient(transport: $transport, resolver: static fn (): array => ['10.0.0.5']);
        $queue  = new PushQueue(sender: new \Modules\Core\Push\PushSender(client: $client));
        $this->assertSame(1, $queue->sendPending()['failed']);
        $this->assertSame([], $transport->sent);
    }

    public function testPreferencesAreRespectedAndMandatoryTypesAreUrgent(): void
    {
        $ana = $this->createUser('ana@example.com');
        $this->device((int) $ana->id);

        service('notifications')->savePreferences((int) $ana->id, [TestNotice::class => ['database', 'email']]);
        notify($ana, new TestNotice());
        $this->assertSame([], $this->queue(), 'Push turned off for the type.');

        notify($ana, new MandatoryNotice());
        $this->assertSame([], $this->queue(), 'The mandatory fixture only uses the database.');

        $this->declareFixtures([['Core', 'notification', TestNotice::class], ['Core', 'notification', UrgentNotice::class]]);
        db_connect()->table('notification_preferences')->insert(['user_id' => $ana->id, 'type' => UrgentNotice::class, 'channels' => '', 'updated_at' => Time::now()->toDateTimeString()]);
        notify($ana, new UrgentNotice());
        $this->assertSame(['high'], array_column($this->queue(), 'urgency'), 'Mandatory types always go to push, with high urgency.');
    }

    public function testSavingPreferencesWhilePushIsHiddenKeepsThePushChoice(): void
    {
        $ana      = $this->createUser('ana@example.com');
        $notifier = service('notifications');

        $notifier->savePreferences((int) $ana->id, [TestNotice::class => ['database']], ['database', 'email']);
        $this->assertSame(['database', 'push'], $notifier->channelsFor((int) $ana->id, TestNotice::class), 'Push (default on) was not in the form: kept.');

        $notifier->savePreferences((int) $ana->id, [TestNotice::class => ['email']]);
        $this->assertSame(['email'], $notifier->channelsFor((int) $ana->id, TestNotice::class));
    }

    public function testGenericPrivacyHidesTheText(): void
    {
        $ana = $this->createUser('ana@example.com');
        $this->device((int) $ana->id);
        setting('Core.pushContent', 'generic');

        notify($ana, new TestNotice(['name' => 'Payroll']));

        $payload = json_decode((string) $this->queue()[0]['payload'], true);
        $this->assertSame(site_name(), $payload['title']);
        $this->assertSame('You have a new notification.', $payload['body']);
        $this->assertSame(backend_url('notifications'), $payload['url']);
        $this->assertStringNotContainsString('Payroll', (string) $this->queue()[0]['payload']);
    }

    public function testNothingIsQueuedOrSentWhilePushIsOff(): void
    {
        $ana = $this->createUser('ana@example.com');
        $this->device((int) $ana->id);
        setting('Core.pushEnabled', false);

        notify($ana, new TestNotice());
        $this->assertSame([], $this->queue());

        // Switched on but without keys: still nothing.
        setting('Core.pushEnabled', true);
        $this->disablePushConfig();
        notify($ana, new TestNotice());
        $this->assertSame([], $this->queue());

        $this->enablePush();
        notify($ana, new TestNotice());
        setting('Core.pushEnabled', false);
        $result = (new SendQueuedPush($this->pushQueue(new FakePushTransport())))->run();
        $this->assertFalse($result->failed());
        $this->assertSame(1, (new PushQueue())->pendingCount(), 'The queue waits while push is off.');
    }

    public function testTheTaskSendsAndReportsAndDeletedUsersLoseTheirDevices(): void
    {
        $ana = $this->createUser('ana@example.com');
        $bob = $this->createUser('bob@example.com');
        $this->device((int) $ana->id);
        $this->device((int) $bob->id);
        notify([$ana, $bob], new TestNotice());

        model(\CodeIgniter\Shield\Models\UserModel::class)->delete($bob->id);
        $transport = new FakePushTransport();
        $result    = (new SendQueuedPush($this->pushQueue($transport)))->run();

        $this->assertFalse($result->failed());
        $this->assertStringContainsString('Sent: 1.', $result->output);
        $this->assertStringContainsString('Devices removed: 1.', $result->output);
        $this->assertSame(0, $this->devices->countFor((int) $bob->id));
        $this->assertCount(1, $transport->sent);

        // Old rows are pruned.
        db_connect()->table('push_queue')->update(['created_at' => Time::now()->subDays(30)->toDateTimeString()]);
        $this->assertSame(1, (new PushQueue())->prune());
    }

    public function testTheTestMessageGoesOnlyToTheUsersOwnDevice(): void
    {
        $ana    = $this->createUser('ana@example.com');
        $bob    = $this->createUser('bob@example.com');
        $device = $this->device((int) $ana->id);

        $this->assertFalse((new \Modules\Core\Notifications\Channels\PushChannel())->test($bob, $device['id']));
        $this->assertTrue((new \Modules\Core\Notifications\Channels\PushChannel())->test($ana, $device['id']));

        $queue = $this->queue();
        $this->assertCount(1, $queue);
        $this->assertSame('high', $queue[0]['urgency']);
        $this->assertSame('Test notification', json_decode((string) $queue[0]['payload'], true)['title']);
    }

    public function testThePushServiceHostsJoinTheAllowListOnlyWhilePushIsOn(): void
    {
        $this->assertTrue((new OutboundAllowList())->allows('fcm.googleapis.com'));
        $this->assertTrue((new OutboundAllowList())->allows('wns2-par02p.notify.windows.com'));

        setting('Core.pushEnabled', false);
        $this->assertFalse((new OutboundAllowList())->allows('fcm.googleapis.com'));
        $this->assertSame([], WebPush::outboundHosts());
    }
}
