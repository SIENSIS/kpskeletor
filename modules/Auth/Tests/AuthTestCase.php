<?php

declare(strict_types=1);

namespace Modules\Auth\Tests;

use CodeIgniter\Events\Events;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Models\UserModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use CodeIgniter\Test\TestResponse;
use Config\Services;
use Modules\Auth\Services\SessionTracker;

/**
 * Base of the Auth feature tests: every namespace migrated (Shield, Settings, Core, Auth),
 * users in groups, requests that carry the session, the cookies and a valid CSRF token, and the
 * emails sent (MockEmail archives).
 */
abstract class AuthTestCase extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

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
