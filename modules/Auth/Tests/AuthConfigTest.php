<?php

declare(strict_types=1);

namespace Modules\Auth\Tests;

use CodeIgniter\Test\CIUnitTestCase;
use Config\Cookie;
use Config\Filters;
use Config\Session;
use Modules\Auth\Config\Module;
use Modules\Core\Modules\ManifestValidator;

/**
 * Cookie, session and filter defaults of the session hardening, and the manifest.
 *
 * @internal
 */
final class AuthConfigTest extends CIUnitTestCase
{
    public function testManifestIsValid(): void
    {
        $this->assertSame([], (new ManifestValidator())->validate(new Module(), dirname(__DIR__)));
    }

    public function testCookiesAreHttpOnlyLaxAndSecureOutsideTesting(): void
    {
        $cookie = new Cookie();

        $this->assertTrue($cookie->httponly);
        $this->assertSame('Lax', $cookie->samesite);
        // Secure is tied to production (the environment here is "testing").
        $this->assertSame(ENVIRONMENT === 'production', $cookie->secure);
        $this->assertStringContainsString("ENVIRONMENT === 'production'", (string) file_get_contents(APPPATH . 'Config/Cookie.php'));
    }

    public function testSessionDestroysOldIdsAndOutlivesTheAbsoluteLifetime(): void
    {
        $session = new Session();

        $this->assertTrue($session->regenerateDestroy);
        $this->assertGreaterThanOrEqual(config('Auth')->absoluteLifetime * MINUTE, $session->expiration);
    }

    public function testCsrfAndSessionFiltersAreGlobal(): void
    {
        $filters = new Filters();

        $this->assertSame(['except' => ['api/*']], $filters->globals['before']['csrf'] ?? null);
        $this->assertContains('authsession', $filters->globals['before']);
        $this->assertSame(['before' => ['backend', 'backend/*']], $filters->filters['backend']);
        $this->assertSame(
            [\CodeIgniter\Shield\Filters\SessionAuth::class, \Modules\Auth\Filters\ForcePasswordReset::class, \Modules\Auth\Filters\ForceTwoFactor::class, \Modules\Core\Filters\RateLimit::class],
            $filters->aliases['backend'],
        );
    }

    public function testTheLoginActionIsTheUserTwoFactorAction(): void
    {
        $this->assertSame(\Modules\Auth\Authentication\UserTwoFactorAction::class, config('Auth')->actions['login']);
        $this->assertSame(15, config('Auth')->reauthMinutes);
        $this->assertSame([], config('Auth')->twoFactorPolicy);
    }
}
