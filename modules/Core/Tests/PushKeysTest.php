<?php

declare(strict_types=1);

namespace Modules\Core\Tests;

use Config\Push;
use Modules\Core\Push\PushKeyWriter;
use Modules\Core\Push\PushSubscriptions;
use Modules\Core\Push\WebPush;
use Modules\Core\Security\Check\Checks\PushCheck;
use Modules\Core\Security\Check\SecurityReport;

/**
 * VAPID keys of the push channel: `push:keys` writes them into `.env` (rotating removes every
 * subscription), `WebPush::problem()` validates them and `security:check` warns when push is on
 * without valid keys (info otherwise).
 *
 * @internal
 */
final class PushKeysTest extends RbacTestCase
{
    use PushFixturesTrait;

    private string $env = '';

    protected function setUp(): void
    {
        parent::setUp();
        $this->env = tempnam(sys_get_temp_dir(), 'kp-env-') ?: '';
        file_put_contents($this->env, "CI_ENVIRONMENT = production\n# push.vapidPublicKey =\n");
        $this->disablePushConfig();
    }

    protected function tearDown(): void
    {
        @unlink($this->env);
        $this->disablePushConfig();
        foreach (['push.vapidPublicKey', 'push.vapidPrivateKey', 'push.subject'] as $key) {
            putenv($key);
            unset($_ENV[$key]);
        }
        parent::tearDown();
    }

    public function testKeysAreWrittenAppliedAndRotationRemovesEveryDevice(): void
    {
        $this->assertSame('missing', WebPush::problem());

        $writer = new PushKeyWriter($this->env);
        $this->assertTrue($writer->envWritable());
        $this->assertSame(0, $writer->write('mailto:owner@example.com'));

        $env = (string) file_get_contents($this->env);
        $this->assertMatchesRegularExpression('/^push\.vapidPublicKey = \'?[A-Za-z0-9_-]{87}\'?$/m', $env);
        $this->assertMatchesRegularExpression('/^push\.vapidPrivateKey = \'?[A-Za-z0-9_-]{43}\'?$/m', $env);
        $this->assertMatchesRegularExpression('/^push\.subject = \'?mailto:owner@example\.com\'?$/m', $env);
        $this->assertStringContainsString("# push.vapidPublicKey =\n", $env, 'Comments are kept.');
        $this->assertNull(WebPush::problem(), 'Applied to the running process.');
        $first = config(Push::class)->vapidPublicKey;

        $ana          = $this->createUser('ana@example.com');
        $subscription = $this->browserSubscription();
        (new PushSubscriptions())->subscribe((int) $ana->id, $subscription['endpoint'], $subscription['keys']['p256dh'], $subscription['keys']['auth'], 'Phone');

        $this->assertSame(1, $writer->write('mailto:other@example.com'));
        $this->assertNotSame($first, config(Push::class)->vapidPublicKey);
        $this->assertSame('mailto:owner@example.com', config(Push::class)->subject, 'A valid contact is kept.');
        $this->assertSame(0, (new PushSubscriptions())->countAll());
        $this->assertSame(1, substr_count((string) file_get_contents($this->env), 'push.vapidPrivateKey ='), 'Replaced, not appended.');
    }

    public function testKeyProblemsAreDetected(): void
    {
        $this->enablePush();
        $config = config(Push::class);
        $this->assertNull(WebPush::problem());

        $other = WebPush::newKeys();
        $copy  = clone $config;
        $copy->vapidPrivateKey = $other['push.vapidPrivateKey'];
        $this->assertSame('mismatch', WebPush::problem($copy));

        $copy = clone $config;
        $copy->vapidPublicKey = 'not-a-key';
        $this->assertSame('invalid', WebPush::problem($copy));

        $copy = clone $config;
        $copy->subject = 'webmaster@example.com';
        $this->assertSame('subject', WebPush::problem($copy));
        $this->assertTrue(WebPush::validSubject('https://www.example.com/contact'));
        $this->assertFalse(WebPush::validSubject('http://www.example.com'));
    }

    public function testSecurityCheckWarnsWhenPushIsOnWithoutKeys(): void
    {
        $empty = new Push();
        $empty->vapidPublicKey = $empty->vapidPrivateKey = $empty->subject = '';

        $report = new SecurityReport();
        (new PushCheck(true, $empty))->run($report);
        $this->assertSame('warning', $report->results()[0]->status);
        $this->assertSame('secrets.pushKeys', $report->results()[0]->id);

        $report = new SecurityReport();
        (new PushCheck(false, $empty))->run($report);
        $this->assertSame('info', $report->results()[0]->status);

        $this->enablePush();
        $report = new SecurityReport();
        (new PushCheck(true, config(Push::class)))->run($report);
        $this->assertSame('info', $report->results()[0]->status);
    }

    public function testTheCommandIsRegistered(): void
    {
        $this->assertArrayHasKey('push:keys', service('commands')->getCommands());
    }
}
