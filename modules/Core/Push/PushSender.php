<?php

declare(strict_types=1);

namespace Modules\Core\Push;

use Config\Push;
use Modules\Core\Http\OutboundClient;
use Modules\Core\Http\OutboundException;
use Modules\Core\Http\OutboundResponse;

/**
 * Sends one Web Push message (RFC 8030) to a device through OutboundClient: the payload
 * encrypted for the browser (PayloadEncryption, `aes128gcm`), the VAPID authorization (Vapid),
 * `TTL` and `Urgency`. The endpoint is checked again against `Config\Push::$serviceHosts` before
 * every request (a row changed in the database still cannot reach another host).
 */
final class PushSender
{
    public function __construct(private readonly ?Push $config = null, private readonly ?OutboundClient $client = null)
    {
    }

    /**
     * @param array<string, mixed> $subscription a `push_subscriptions` row
     *
     * @throws OutboundException
     */
    public function send(array $subscription, string $payload, string $urgency = PushQueue::URGENCY_NORMAL): OutboundResponse
    {
        $config   = $this->config ?? config(Push::class);
        $endpoint = (string) $subscription['endpoint'];

        if (PushEndpoint::problem($endpoint, $config->serviceHosts) !== null) {
            throw new OutboundException(OutboundException::REASON_HOST, 'The push endpoint is not on a known push service.');
        }

        $body = PayloadEncryption::encrypt(
            $payload,
            (string) Base64Url::decode((string) $subscription['p256dh']),
            (string) Base64Url::decode((string) $subscription['auth']),
        );

        return ($this->client ?? new OutboundClient())->post($endpoint, $body, [
            'Content-Type'     => 'application/octet-stream',
            'Content-Encoding' => 'aes128gcm',
            'TTL'              => (string) $config->ttl,
            'Urgency'          => $urgency === PushQueue::URGENCY_HIGH ? 'high' : 'normal',
            'Authorization'    => WebPush::vapid($config)->authorization($endpoint),
        ]);
    }
}
