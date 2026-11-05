<?php

declare(strict_types=1);

namespace Modules\Auth\Tests;

use Modules\Auth\Services\Captcha;

/**
 * The local captcha: off by default, enforced in every environment when on, answer hashed in
 * the session, single use.
 *
 * @internal
 */
final class CaptchaTest extends AuthTestCase
{
    public function testImageIs404WhileOff(): void
    {
        $this->expectException(\CodeIgniter\Exceptions\PageNotFoundException::class);

        $this->send('GET', 'auth/captcha');
    }

    public function testImageIsAnUncachedPngWhenOn(): void
    {
        config('Auth')->captcha = true;

        $response = $this->send('GET', 'auth/captcha');

        $response->assertOK();
        $this->assertSame('image/png', $response->response()->getHeaderLine('Content-Type'));
        $this->assertStringContainsString('no-store', $response->response()->getHeaderLine('Cache-Control'));
        $this->assertStringStartsWith("\x89PNG", (string) $response->response()->getBody());
        $this->assertIsArray($_SESSION[Captcha::SESSION_KEY]);
    }

    public function testAnswerIsHashedAndSingleUse(): void
    {
        $captcha   = new Captcha();
        $challenge = $captcha->newChallenge();
        $stored    = session(Captcha::SESSION_KEY);

        $this->assertIsArray($stored);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $stored['hash']);
        $this->assertArrayNotHasKey('answer', $stored);
        $this->assertTrue($captcha->verify((string) $challenge['answer']));
        $this->assertFalse($captcha->verify((string) $challenge['answer']), 'A challenge can only be checked once.');
    }

    public function testWrongAnswerAlsoConsumesTheChallenge(): void
    {
        $captcha   = new Captcha();
        $challenge = $captcha->newChallenge();

        $this->assertFalse($captcha->verify((string) ($challenge['answer'] + 1)));
        $this->assertFalse($captcha->verify((string) $challenge['answer']));
        $this->assertFalse($captcha->verify(null));
        $this->assertFalse($captcha->verify('1 OR 1=1'));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function environments(): iterable
    {
        yield 'testing' => ['testing'];

        yield 'development' => ['development'];
    }

    /**
     * Enforced whatever the environment: the check never looks at ENVIRONMENT.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('environments')]
    public function testLoginNeedsTheCaptchaWhenOn(string $environment): void
    {
        config('Auth')->captcha = true;
        $this->createUser();

        $this->send('GET', 'login')->assertSee(lang('Auth.captcha.label'));

        $response = $this->login('ana@example.com');

        $response->assertSee(lang('Auth.msg.captchaWrong'));
        $this->assertNull($this->loggedInUserId(), "Refused without a captcha ({$environment}).");

        $session                         = $_SESSION;
        $challenge                       = (new Captcha())->newChallenge();
        $session[Captcha::SESSION_KEY]   = $_SESSION[Captcha::SESSION_KEY];
        $response                        = $this->send('POST', 'login', ['email' => 'ana@example.com', 'password' => self::$password, 'captcha' => (string) $challenge['answer']], $session);

        $response->assertRedirect();
        $this->assertNotNull($this->loggedInUserId());
    }
}
