<?php

declare(strict_types=1);

namespace Modules\Core\Tests;

use Config\Push;
use Modules\Core\Http\OutboundClient;
use Modules\Core\Push\Base64Url;
use Modules\Core\Push\EcKey;
use Modules\Core\Push\PushQueue;
use Modules\Core\Push\PushSender;
use Modules\Core\Push\WebPush;

/**
 * Web Push in tests: the channel switched on with fresh VAPID keys, browser-side key pairs for
 * fake devices (so the tests decrypt what the server sends), and a queue whose sender goes
 * through the real OutboundClient with a fake transport.
 */
trait PushFixturesTrait
{
    /** @var array<string, array{public: string, private: string, auth: string}> endpoint => browser keys */
    protected array $browserKeys = [];

    protected function enablePush(bool $on = true): void
    {
        $keys   = WebPush::newKeys();
        $config = config(Push::class);
        $config->vapidPublicKey  = $keys['push.vapidPublicKey'];
        $config->vapidPrivateKey = $keys['push.vapidPrivateKey'];
        $config->subject         = 'mailto:webmaster@example.com';
        WebPush::reset();
        setting('Core.pushEnabled', $on);
        setting('Core.pushContent', 'full');
    }

    protected function disablePushConfig(): void
    {
        $config = config(Push::class);
        $config->vapidPublicKey  = '';
        $config->vapidPrivateKey = '';
        $config->subject         = '';
        WebPush::reset();
    }

    /**
     * What a browser's `PushSubscription.toJSON()` sends.
     *
     * @return array{endpoint: string, keys: array{p256dh: string, auth: string}}
     */
    protected function browserSubscription(string $endpoint = ''): array
    {
        $endpoint = $endpoint !== '' ? $endpoint : 'https://fcm.googleapis.com/fcm/send/' . bin2hex(random_bytes(12));
        $keys     = EcKey::generate();
        $auth     = random_bytes(16);
        $this->browserKeys[$endpoint] = $keys + ['auth' => $auth];

        return ['endpoint' => $endpoint, 'keys' => ['p256dh' => Base64Url::encode($keys['public']), 'auth' => Base64Url::encode($auth)]];
    }

    protected function pushQueue(FakePushTransport $transport): PushQueue
    {
        $client = new OutboundClient(transport: $transport, resolver: static fn (string $host): array => ['142.250.200.10']);

        return new PushQueue(sender: new PushSender(client: $client));
    }
}
