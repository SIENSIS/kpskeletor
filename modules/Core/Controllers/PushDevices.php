<?php

declare(strict_types=1);

namespace Modules\Core\Controllers;

use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;
use Modules\Core\Notifications\Channels\PushChannel;
use Modules\Core\Push\DeviceLabel;
use Modules\Core\Push\PushSubscriptions;
use Modules\Core\Push\WebPush;

/**
 * The logged-in user's push devices (D-44-02): personal routes under `backend/profile/push` with
 * `can:core-notifications.list` (every group), CSRF and `ratelimit:forms` on the POSTs. A user
 * only lists, tests, renames and removes their own devices; the scripts call these routes with
 * JSON (`core/push.js`) and the forms of Profile › Notifications post them without JavaScript.
 */
final class PushDevices extends BackendController
{
    /**
     * GET: the state of the channel and the user's devices (JSON).
     */
    public function index(): ResponseInterface
    {
        return $this->response->setJSON($this->state());
    }

    /**
     * POST: stores the browser's subscription (`{endpoint, keys: {p256dh, auth}}`).
     */
    public function subscribe(): ResponseInterface
    {
        if (! WebPush::enabled()) {
            return $this->response->setStatusCode(404)->setJSON(['message' => lang('Push.off')]);
        }

        $data     = $this->request->getJSON(true);
        $data     = is_array($data) ? $data : [];
        $keys     = is_array($data['keys'] ?? null) ? $data['keys'] : [];
        $endpoint = is_string($data['endpoint'] ?? null) ? $data['endpoint'] : '';
        $label    = DeviceLabel::fromUserAgent((string) $this->request->getUserAgent());
        $result   = (new PushSubscriptions())->subscribe(
            (int) auth()->id(),
            $endpoint,
            is_string($keys['p256dh'] ?? null) ? $keys['p256dh'] : '',
            is_string($keys['auth'] ?? null) ? $keys['auth'] : '',
            $label,
        );

        if (isset($result['error'])) {
            $parts = parse_url($endpoint);
            security_log('Core', 'push_subscription_refused', [
                'resource' => 'core-notifications',
                'summary'  => is_array($parts) ? (string) ($parts['host'] ?? '') : '',
                'changes'  => ['reason' => $result['error']],
            ]);

            return $this->response->setStatusCode(422)->setJSON(['message' => lang('Push.refused.' . $result['error'])] + $this->state());
        }

        activity_log('Core', 'push_subscribed', ['resource' => 'core-notifications', 'record_id' => $result['id'], 'summary' => $label]);

        return $this->response->setJSON(['message' => lang('Push.subscribed'), 'id' => $result['id']] + $this->state());
    }

    public function delete(string $id): ResponseInterface|RedirectResponse
    {
        $done = (new PushSubscriptions())->delete((int) auth()->id(), (int) $id);

        if ($done) {
            activity_log('Core', 'push_unsubscribed', ['resource' => 'core-notifications', 'record_id' => (int) $id]);
        }

        return $this->answer($done, 'Push.removed');
    }

    /**
     * Queues a test message for the device (sent by the scheduled task within a minute).
     */
    public function test(string $id): ResponseInterface|RedirectResponse
    {
        $user = $this->currentUser();

        return $this->answer((new PushChannel())->test($user, (int) $id), 'Push.test.queued');
    }

    public function label(string $id): ResponseInterface|RedirectResponse
    {
        $data  = $this->request->getJSON(true);
        $label = is_array($data) && is_string($data['label'] ?? null) ? $data['label'] : (string) $this->request->getPost('label');

        return $this->answer((new PushSubscriptions())->rename((int) auth()->id(), (int) $id, $label), 'Push.renamed');
    }

    /**
     * @return array{enabled: bool, publicKey: string, devices: list<array<string, mixed>>}
     */
    private function state(): array
    {
        $enabled = WebPush::enabled();
        $devices = [];

        foreach ((new PushSubscriptions())->forUser((int) auth()->id()) as $device) {
            $devices[] = [
                'id'       => $device['id'],
                'label'    => $device['label'],
                'created'  => DeviceLabel::date($device['created_at']),
                'lastUsed' => DeviceLabel::date($device['last_used_at']),
                // The browser compares it with the SHA-256 of its own endpoint ("this device").
                'hash'     => $device['endpoint_hash'],
            ];
        }

        return [
            'enabled'   => $enabled,
            'publicKey' => $enabled ? (string) config('Push')->vapidPublicKey : '',
            'devices'   => $devices,
        ];
    }

    private function answer(bool $done, string $message): ResponseInterface|RedirectResponse
    {
        $wantsJson = str_contains((string) $this->request->getHeaderLine('Accept'), 'application/json') || $this->request->isAJAX();

        if ($wantsJson) {
            return $this->response->setStatusCode($done ? 200 : 404)->setJSON(['message' => lang($done ? $message : 'Push.notFound')] + $this->state());
        }

        return redirect()->to(backend_url('profile/notifications'))->with($done ? 'success' : 'error', lang($done ? $message : 'Push.notFound'));
    }
}
