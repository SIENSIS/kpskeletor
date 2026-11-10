<?php

declare(strict_types=1);

namespace Modules\Auth\Tests;

use CodeIgniter\Events\Events;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Models\UserModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use CodeIgniter\Test\TestResponse;
use Config\Services;
use Modules\Auth\Services\SessionTracker;
use Modules\Core\Tests\RbacFixtureTrait;

/**
 * Base of the Auth feature tests: every namespace migrated (Shield, Settings, Core, Auth),
 * the permission catalogue, default grants and Shield's matrix as `modules:sync` leaves them,
 * users in groups, requests that carry the session, the cookies and a valid CSRF token, and the
 * emails sent (MockEmail archives).
 */
abstract class AuthTestCase extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;
    use RbacFixtureTrait;

    protected $migrate     = true;
    protected $migrateOnce = false;
    protected $refresh     = true;
    protected $namespace   = null;

    protected static string $password = 'Correct-Horse-Battery-42';

    /** @var list<array<string, mixed>> */
    protected array $sentEmails = [];

    /** @var array<string, string> */
    protected array $cookies = [];

    protected function setUp(): void
    {
        parent::setUp();
        // Shared services that keep a config instance across tests (factories are reset).
        Services::resetSingle('passwords');
        Services::resetSingle('settings');
        service('language')->setLocale('en');
        SessionTracker::reset();
        $this->sentEmails = [];
        $this->cookies    = [];
        Events::on('email', function (array $archive): void {
            $this->sentEmails[] = $archive;
        });
        service('superglobals')->setCookieArray([]);
        service('superglobals')->setServer('REMOTE_ADDR', '192.0.2.10');
        $this->syncPermissions();
    }

    /**
     * Leaves no logged-in user behind for the next test class (the session and Shield's shared
     * authenticator outlive the test otherwise).
     */
    protected function tearDown(): void
    {
        $_SESSION = [];
        Services::resetSingle('auth');
        service('superglobals')->setCookieArray([]);
        parent::tearDown();
    }

    protected function createUser(string $email = 'ana@example.com', string $group = 'user', ?string $password = null): User
    {
        $users = model(UserModel::class);
        $user  = new User([
            'username' => strstr($email, '@', true) ?: 'user',
            'email'    => $email,
            'password' => $password ?? self::$password,
            'active'   => 1,
        ]);
        $users->save($user);

        $user = $users->findById($users->getInsertID());
        $this->assertInstanceOf(User::class, $user);
        $user->addGroup($group);

        return $user;
    }

    /**
     * A request with the current session (or a given one) and the cookies kept by the test.
     *
     * @param array<string, mixed>      $params
     * @param array<string, mixed>|null $session
     */
    protected function send(string $method, string $path, array $params = [], ?array $session = null): TestResponse
    {
        $session ??= $_SESSION ?? [];

        if ($method === 'POST' && ! array_key_exists('csrf_test_name', $params)) {
            $session['csrf_test_name'] = 'test-csrf-token';
            $params['csrf_test_name']  = 'test-csrf-token';
        }

        Services::resetSingle('security');
        Services::resetSingle('response');
        Services::resetSingle('auth');
        service('superglobals')->setCookieArray($this->cookies);

        $response = $this->withSession($session)->call($method, $path, $params);

        foreach ($response->response()->getCookies() as $cookie) {
            if ($cookie->getValue() === '' || $cookie->isExpired()) {
                unset($this->cookies[$cookie->getPrefixedName()]);
            } else {
                $this->cookies[$cookie->getPrefixedName()] = $cookie->getValue();
            }
        }

        return $response;
    }

    /**
     * A user of the `user` group with these individual permissions, logged in.
     */
    protected function loginWith(string ...$permissions): User
    {
        $user = $this->createUser('ana@example.com', 'user');

        if ($permissions !== []) {
            $user->addPermission(...$permissions);
        }

        $this->login('ana@example.com');

        return $user;
    }

    /**
     * The request answers 404 (a feature test gets the exception, a real request the error page).
     *
     * @param array<string, mixed> $params
     */
    protected function assertNotFound(string $method, string $path, array $params = []): void
    {
        try {
            $status = $this->send($method, $path, $params)->response()->getStatusCode();
        } catch (PageNotFoundException) {
            $status = 404;
        }

        $this->assertSame(404, $status, "{$method} {$path}");
    }

    /**
     * kpcrudy refused the request: a redirect to the CRUD's list with its "not allowed" (or
     * "invalid request", e.g. a GET to a POST-only operation) message and no success message.
     * A plain redirect is not enough: kpcrudy also redirects after a successful write.
     */
    protected function assertCrudRefused(TestResponse $response, string $listPath, string $what = ''): void
    {
        $this->assertTrue($response->isRedirect(), "{$what}: redirected to the list.");
        $this->assertSame(site_url($listPath), $response->response()->getHeaderLine('Location'), $what);
        $this->assertContains($_SESSION['error'] ?? null, [lang('Kpcrudy.notAllowed'), lang('Kpcrudy.invalidRequest')], "{$what}: refused with an error message.");
        $this->assertArrayNotHasKey('success', $_SESSION, "{$what}: no success message.");
    }

    protected function login(string $email, ?string $password = null, bool $remember = false): TestResponse
    {
        $params = ['email' => $email, 'password' => $password ?? self::$password];

        if ($remember) {
            $params['remember'] = '1';
        }

        return $this->send('POST', 'login', $params, []);
    }

    protected function loggedInUserId(): ?int
    {
        $info = $_SESSION['user'] ?? null;

        return is_array($info) && isset($info['id']) ? (int) $info['id'] : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function lastEmail(): ?array
    {
        return $this->sentEmails === [] ? null : $this->sentEmails[array_key_last($this->sentEmails)];
    }

    protected function linkToken(string $route): string
    {
        $email = $this->lastEmail();
        $this->assertNotNull($email, 'No email was sent.');
        $this->assertSame(1, preg_match('#' . preg_quote($route, '#') . '\?token=([a-f0-9]{48})#', (string) $email['body'], $match));

        return $match[1];
    }
}
