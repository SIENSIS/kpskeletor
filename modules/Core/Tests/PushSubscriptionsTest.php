<?php

declare(strict_types=1);

namespace Modules\Core\Tests;

use CodeIgniter\Test\TestResponse;
use Modules\Auth\Tests\AuthTestCase;
use Modules\Core\Push\DeviceLabel;
use Modules\Core\Push\PushEndpoint;
use Modules\Core\Push\PushSubscriptions;
use Tests\Support\Scheduler\DeclaresFixtures;
use Tests\Support\Scheduler\TestNotice;

/**
 * The personal push routes under `backend/profile/push` (D-44-02): only HTTPS endpoints of the
 * known push services are stored (SSRF), a user lists, tests, renames and removes only their own
 * devices, CSRF; Profile › Notifications shows the devices and the push column only while push
 * is on; the service worker is served for push even with the PWA off.
 *
 * @internal
 */
final class PushSubscriptionsTest extends AuthTestCase
{
    use DeclaresFixtures;
    use PushFixturesTrait;

    private const FIREFOX = 'Mozilla/5.0 (X11; Linux x86_64; rv:132.0) Gecko/20100101 Firefox/132.0';

    protected function setUp(): void
    {
        parent::setUp();
        service('language')->setLocale('en');
        $this->declareFixtures([['Core', 'notification', TestNotice::class]]);
        $this->enablePush();
    }

    protected function tearDown(): void
    {
        $this->disablePushConfig();
        parent::tearDown();
    }

    /**
     * @param array<string, mixed> $json
     */
    private function postJson(string $path, array $json = []): TestResponse
    {
        service('superglobals')->setServer('HTTP_USER_AGENT', self::FIREFOX);
        $json['csrf_test_name'] = 'test-csrf-token';
        $session                = array_merge($_SESSION ?? [], ['csrf_test_name' => 'test-csrf-token']);

        $response = $this->withBodyFormat('json')
            ->withHeaders(['Accept' => 'application/json', 'X-Requested-With' => 'XMLHttpRequest', 'User-Agent' => self::FIREFOX])
            ->send('POST', $path, $json, $session);
        $this->withBodyFormat('')->withHeaders([]);

        return $response;
    }

    /**
     * @return array<string, mixed>
     */
    private function json(TestResponse $response): array
    {
        $data = json_decode((string) $response->response()->getBody(), true);
        $this->assertIsArray($data);

        return $data;
    }

    private function devices(): int
    {
        return db_connect()->table('push_subscriptions')->countAllResults();
    }

    public function testAValidSubscriptionIsStoredWithALabelAndLogged(): void
    {
        $this->createUser('ana@example.com');
        $this->login('ana@example.com');
        $subscription = $this->browserSubscription();

        $response = $this->postJson('backend/profile/push/subscribe', $subscription);

        $response->assertOK();
        $data = $this->json($response);
        $this->assertTrue($data['enabled']);
        $this->assertSame(config('Push')->vapidPublicKey, $data['publicKey']);
        $this->assertCount(1, $data['devices']);
        $this->assertSame('Firefox · Linux', $data['devices'][0]['label']);
        $this->assertSame(hash('sha256', $subscription['endpoint']), $data['devices'][0]['hash']);
        $this->assertStringNotContainsString($subscription['keys']['auth'], (string) $response->response()->getBody(), 'Keys and endpoints never go back to the page.');
        $this->assertStringNotContainsString('fcm.googleapis.com', (string) $response->response()->getBody());
        $this->assertSame(1, db_connect()->table('activity_log')->where('action', 'push_subscribed')->countAllResults());

        // The same browser again: still one device.
        $this->postJson('backend/profile/push/subscribe', $subscription)->assertOK();
        $this->assertSame(1, $this->devices());
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function forgedEndpoints(): iterable
    {
        yield 'another host' => ['https://evil.example.com/push/abc', 'host'];
        yield 'look-alike host' => ['https://fcm.googleapis.com.evil.example/push', 'host'];
        yield 'plain http' => ['http://fcm.googleapis.com/fcm/send/abc', 'scheme'];
        yield 'another port' => ['https://fcm.googleapis.com:8443/fcm/send/abc', 'scheme'];
        yield 'private IP' => ['https://10.0.0.5/push', 'host'];
        yield 'loopback' => ['https://127.0.0.1/push', 'host'];
        yield 'IPv6 loopback' => ['https://[::1]/push', 'host'];
        yield 'metadata service' => ['https://169.254.169.254/latest/meta-data', 'host'];
        yield 'local name' => ['https://localhost/push', 'host'];
        yield 'credentials' => ['https://user:pass@fcm.googleapis.com/fcm/send/abc', 'url'];
        yield 'not a URL' => ['javascript:alert(1)', 'url'];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('forgedEndpoints')]
    public function testForgedEndpointsAreRefused(string $endpoint, string $reason): void
    {
        $this->assertSame($reason, PushEndpoint::problem($endpoint));

        $this->createUser('ana@example.com');
        $this->login('ana@example.com');
        $response = $this->postJson('backend/profile/push/subscribe', $this->browserSubscription($endpoint));

        $response->assertStatus(422);
        $this->assertSame(lang('Push.refused.' . $reason), $this->json($response)['message']);
        $this->assertSame(0, $this->devices());
        $this->assertSame(1, db_connect()->table('activity_log')->where('action', 'push_subscription_refused')->countAllResults(), 'A security log entry.');
    }

    public function testTheKnownPushServicesAreAccepted(): void
    {
        foreach ([
            'https://fcm.googleapis.com/fcm/send/abc:APA91b',
            'https://updates.push.services.mozilla.com/wpush/v2/gAAAAA',
            'https://wns2-par02p.notify.windows.com/w/?token=BQYAAA',
            'https://web.push.apple.com/QGuQyavXutnMH',
        ] as $endpoint) {
            $this->assertNull(PushEndpoint::problem($endpoint), $endpoint);
        }

        $this->assertSame('host', PushEndpoint::problem('https://notify.windows.com/x'), 'A wildcard covers subdomains only.');
    }

    public function testInvalidKeysAndTooManyDevicesAreRefused(): void
    {
        $this->createUser('ana@example.com');
        $this->login('ana@example.com');
        $subscription                   = $this->browserSubscription();
        $subscription['keys']['p256dh'] = 'BAAA';

        $this->postJson('backend/profile/push/subscribe', $subscription)->assertStatus(422);
        $this->assertSame(0, $this->devices());

        config('Push')->maxDevices = 1;
        $this->postJson('backend/profile/push/subscribe', $this->browserSubscription())->assertOK();
        $response = $this->postJson('backend/profile/push/subscribe', $this->browserSubscription());
        config('Push')->maxDevices = 20;

        $response->assertStatus(422);
        $this->assertSame(lang('Push.refused.limit'), $this->json($response)['message']);
    }

    public function testSubscribingWhilePushIsOffAnswers404(): void
    {
        setting('Core.pushEnabled', false);
        $this->createUser('ana@example.com');
        $this->login('ana@example.com');

        $this->postJson('backend/profile/push/subscribe', $this->browserSubscription())->assertStatus(404);
        $this->assertSame(0, $this->devices());
    }

    public function testAUserOnlyListsAndChangesTheirOwnDevices(): void
    {
        $ana     = $this->createUser('ana@example.com');
        $bob     = $this->createUser('bob@example.com');
        $devices = new PushSubscriptions();
        $mine    = $this->browserSubscription();
        $theirs  = $this->browserSubscription();
        $anaId   = $devices->subscribe((int) $ana->id, $mine['endpoint'], $mine['keys']['p256dh'], $mine['keys']['auth'], 'Ana phone')['id'] ?? 0;
        $bobId   = $devices->subscribe((int) $bob->id, $theirs['endpoint'], $theirs['keys']['p256dh'], $theirs['keys']['auth'], 'Bob laptop')['id'] ?? 0;

        $this->login('ana@example.com');
        $list = $this->send('GET', 'backend/profile/push');
        $list->assertOK();
        $this->assertSame(['Ana phone'], array_column($this->json($list)['devices'], 'label'));

        foreach (['delete', 'test', 'label'] as $action) {
            $this->postJson('backend/profile/push/' . $action . '/' . $bobId, ['label' => 'Mine now'])->assertStatus(404);
        }

        $this->assertNotNull($devices->find((int) $bob->id, $bobId));
        $this->assertSame('Bob laptop', $devices->find((int) $bob->id, $bobId)['label']);
        $this->assertSame(0, db_connect()->table('push_queue')->countAllResults());

        // Ana's own device.
        $this->postJson('backend/profile/push/label/' . $anaId, ['label' => "Kitchen\ntablet"])->assertOK();
        $this->assertSame('Kitchen tablet', $devices->find((int) $ana->id, $anaId)['label']);
        $this->postJson('backend/profile/push/test/' . $anaId)->assertOK();
        $this->assertSame(1, db_connect()->table('push_queue')->countAllResults(), 'Queued, not sent inline.');
        $this->postJson('backend/profile/push/delete/' . $anaId)->assertOK();
        $this->assertNull($devices->find((int) $ana->id, $anaId));
        $this->assertSame(0, db_connect()->table('push_queue')->countAllResults(), 'Its queued messages go with it.');
    }

    public function testFormsWithoutJavaScriptRedirectBack(): void
    {
        $ana    = $this->createUser('ana@example.com');
        $device = $this->browserSubscription();
        $id     = (new PushSubscriptions())->subscribe((int) $ana->id, $device['endpoint'], $device['keys']['p256dh'], $device['keys']['auth'], 'Phone')['id'] ?? 0;
        $this->login('ana@example.com');

        $this->send('POST', 'backend/profile/push/delete/' . $id)->assertRedirectTo(site_url('backend/profile/notifications'));
        $this->assertSame(0, $this->devices());
    }

    public function testAForgedPostWithoutTheCsrfTokenIsRefused(): void
    {
        $ana    = $this->createUser('ana@example.com');
        $device = $this->browserSubscription();
        $id     = (new PushSubscriptions())->subscribe((int) $ana->id, $device['endpoint'], $device['keys']['p256dh'], $device['keys']['auth'], 'Phone')['id'] ?? 0;
        $this->login('ana@example.com');

        try {
            $this->send('POST', 'backend/profile/push/delete/' . $id, ['csrf_test_name' => 'forged'], array_merge($_SESSION ?? [], ['csrf_test_name' => 'test-csrf-token']));
            $this->fail('The CSRF filter refuses it.');
        } catch (\CodeIgniter\Security\Exceptions\SecurityException) {
        }

        $this->assertSame(1, $this->devices());
    }

    public function testGuestsAreSentToTheLogin(): void
    {
        $this->send('GET', 'backend/profile/push')->assertRedirect();
        $this->send('POST', 'backend/profile/push/subscribe', $this->browserSubscription())->assertRedirect();
        $this->assertSame(0, $this->devices());
    }

    public function testABrowserSubscribedByAnotherAccountMovesWithoutItsMessages(): void
    {
        $ana          = $this->createUser('ana@example.com');
        $this->createUser('bob@example.com');
        $subscription = $this->browserSubscription();
        $id           = (new PushSubscriptions())->subscribe((int) $ana->id, $subscription['endpoint'], $subscription['keys']['p256dh'], $subscription['keys']['auth'], 'Shared PC')['id'] ?? 0;
        notify($ana, new TestNotice(['name' => 'Private']));
        $this->assertSame(1, db_connect()->table('push_queue')->countAllResults());

        $this->login('bob@example.com');
        $this->postJson('backend/profile/push/subscribe', $subscription)->assertOK();

        $row = db_connect()->table('push_subscriptions')->where('id', $id)->get()->getRowArray();
        $this->assertNotSame((int) $ana->id, (int) $row['user_id']);
        $this->assertSame(0, db_connect()->table('push_queue')->countAllResults(), "Ana's queued message never reaches Bob.");
    }

    public function testThePreferencesPageShowsDevicesAndThePushColumnOnlyWhilePushIsOn(): void
    {
        $this->createUser('ana@example.com');
        $this->login('ana@example.com');

        $body = (string) $this->send('GET', 'backend/profile/notifications')->response()->getBody();
        $this->assertStringContainsString('data-component="core/push"', $body);
        $this->assertStringContainsString(lang('Push.enable'), $body);
        $this->assertStringContainsString('value="push"', $body);
        $this->assertStringContainsString('&quot;push&quot;:true', htmlspecialchars((string) page_config_json()), 'The page configuration tells core/pwa.js to keep the worker.');

        setting('Core.pushEnabled', false);
        $body = (string) $this->send('GET', 'backend/profile/notifications')->response()->getBody();
        $this->assertStringNotContainsString('data-component="core/push"', $body);
        $this->assertStringNotContainsString('value="push"', $body);
    }

    public function testTheServiceWorkerIsServedForPushEvenWithThePwaOff(): void
    {
        setting('Core.pwa', false);

        $response = $this->send('GET', 'sw.js');
        $response->assertOK();
        $this->assertStringContainsString("addEventListener('push'", (string) $response->response()->getBody());
        $this->assertStringContainsString("addEventListener('notificationclick'", (string) $response->response()->getBody());
        $this->assertNotFound('GET', 'manifest.webmanifest');

        setting('Core.pushEnabled', false);
        $this->assertNotFound('GET', 'sw.js');
    }

    public function testDeviceLabels(): void
    {
        $this->assertSame('Firefox · Linux', DeviceLabel::fromUserAgent(self::FIREFOX));
        $this->assertSame('Chrome · Android', DeviceLabel::fromUserAgent('Mozilla/5.0 (Linux; Android 14; Pixel 8) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0 Mobile Safari/537.36'));
        $this->assertSame('Safari · iPhone', DeviceLabel::fromUserAgent('Mozilla/5.0 (iPhone; CPU iPhone OS 17_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.5 Mobile/15E148 Safari/604.1'));
        $this->assertSame('Edge · Windows', DeviceLabel::fromUserAgent('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0 Safari/537.36 Edg/131.0'));
        $this->assertSame(lang('Push.device.unknown'), DeviceLabel::fromUserAgent('curl/8'));
        $this->assertSame(100, mb_strlen(DeviceLabel::clean(str_repeat('é', 150))));
    }
}
