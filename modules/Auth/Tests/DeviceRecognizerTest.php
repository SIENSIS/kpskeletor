<?php

declare(strict_types=1);

namespace Modules\Auth\Tests;

use Modules\Auth\Models\KnownDeviceModel;
use Modules\Auth\Services\DeviceRecognizer;

/**
 * New-device notices: the first device of an account is silent, a new device or browser gets an
 * email with a link to "my sessions", a known device none; only the cookie's hash is stored.
 *
 * @internal
 */
final class DeviceRecognizerTest extends AuthTestCase
{
    public function testNewDeviceGetsANoticeAndKnownDevicesDoNot(): void
    {
        $user = $this->createUser();

        $this->login('ana@example.com');
        $this->assertSame([], $this->sentEmails, 'The first device of an account is not reported.');
        $cookie = $this->cookies[DeviceRecognizer::COOKIE] ?? null;
        $this->assertIsString($cookie);

        $row = model(KnownDeviceModel::class)->where('user_id', $user->id)->first();
        $this->assertSame(hash('sha256', $cookie), $row['device_hash']);

        // Same browser again: no notice.
        $this->send('POST', 'logout');
        $this->login('ana@example.com');
        $this->assertSame([], $this->sentEmails);

        // Another browser (no device cookie): notice with a link to "my sessions".
        $this->cookies = [];
        $this->login('ana@example.com');

        $this->assertCount(1, $this->sentEmails);
        $this->assertSame(['ana@example.com'], $this->lastEmail()['recipients'] ?? null);
        $this->assertStringContainsString(site_url('backend/profile/sessions'), (string) ($this->lastEmail()['body'] ?? ''));
        $this->assertSame(2, model(KnownDeviceModel::class)->where('user_id', $user->id)->countAllResults());
    }

    public function testNoticeCanBeTurnedOff(): void
    {
        config('Auth')->newDeviceNotice = false;
        $this->createUser();

        $this->login('ana@example.com');
        $this->cookies = [];
        $this->login('ana@example.com');

        $this->assertSame([], $this->sentEmails);
    }

    public function testDeviceCookieIsHttpOnly(): void
    {
        $this->createUser();
        $response = $this->login('ana@example.com');
        $cookie   = $response->response()->getCookie(DeviceRecognizer::COOKIE);

        $this->assertNotNull($cookie);
        $this->assertTrue($cookie->isHTTPOnly());
        $this->assertSame('Lax', $cookie->getSameSite());
    }
}
