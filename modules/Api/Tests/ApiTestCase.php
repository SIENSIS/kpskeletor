<?php

declare(strict_types=1);

namespace Modules\Api\Tests;

use CodeIgniter\Config\Factories;
use CodeIgniter\Config\Services;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Test\Mock\MockCache;
use CodeIgniter\Test\TestResponse;
use Kpapy\Auth\Context;
use Kpapy\Auth\TokenService;
use Modules\Auth\Tests\TwoFactorTestCase;

/**
 * Base of the API feature tests: the Auth fixtures (users, emails, encryption key, fixed clock,
 * TOTP helpers), a fresh in-memory cache per test (rate limits and login throttling), a fresh
 * kpapy configuration, and JSON requests with or without a bearer token and without any session.
 */
abstract class ApiTestCase extends TwoFactorTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Services::injectMock('cache', new MockCache());
        Services::resetSingle('throttler');
        Factories::reset('config');
        config('Encryption')->key = str_repeat("\x42", 32);
        Services::resetSingle('encrypter');
        Context::reset();
    }

    protected function tearDown(): void
    {
        Context::reset();
        Services::resetSingle('cache');
        Services::resetSingle('throttler');
        parent::tearDown();
    }

    /**
     * A JSON API request: no session, no cookies, an optional bearer token.
     *
     * @param array<string, mixed>|null $body
     * @param array<string, string>     $headers
     */
    protected function api(string $method, string $path, ?array $body = null, ?string $token = null, array $headers = []): TestResponse
    {
        Services::resetSingle('response');
        Services::resetSingle('auth');
        Context::reset();
        $_SESSION = [];
        service('superglobals')->setCookieArray([]);

        $headers += ['Accept' => 'application/json'];

        if ($token !== null) {
            $headers['Authorization'] = 'Bearer ' . $token;
        }

        if ($body !== null) {
            $headers['Content-Type'] = 'application/json';
            $this->withBody((string) json_encode($body));
        } else {
            $this->requestBody = null;
        }

        $response = $this->withHeaders($headers)->call($method, $path);
        $this->withHeaders([]);
        $this->requestBody = null; // the next form request (send()) uses its parameters again

        return $response;
    }

    /**
     * @return array<string, mixed>
     */
    protected function json(TestResponse $response): array
    {
        $data = json_decode((string) $response->response()->getBody(), true);

        return is_array($data) ? $data : [];
    }

    /**
     * A personal-style token for a user (kpapy's TokenService).
     *
     * @param list<string> $scopes
     */
    protected function tokenFor(User $user, array $scopes = ['*']): string
    {
        return (new TokenService())->create($user, 'test', $scopes)['token'];
    }

    /**
     * Step 1 of the app login; returns the decoded body.
     *
     * @return array<string, mixed>
     */
    protected function startLogin(string $email, ?string $password = null): array
    {
        $response = $this->api('POST', 'api/v1/auth/token', ['email' => $email, 'password' => $password ?? self::$password, 'device_name' => 'Test phone']);
        $response->assertStatus(202);

        return $this->json($response)['data'];
    }
}
