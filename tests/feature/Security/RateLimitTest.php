<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use CodeIgniter\Config\Factories;
use CodeIgniter\Test\Mock\MockCache;
use InvalidArgumentException;
use Modules\Auth\Tests\AuthTestCase;
use Modules\Core\Config\Module;
use Modules\Core\Filters\RateLimit;
use Modules\Core\Security\RateLimiter;

/**
 * The rate-limit service (named limits, capacities in the Core settings) and the `ratelimit`
 * filter of the `backend` chain: past the limit, 429 with Retry-After.
 *
 * @internal
 */
final class RateLimitTest extends AuthTestCase
{
    public function testNamedLimitsUseTheirSettings(): void
    {
        $settings = ['Core.rateLimitFeeds' => 3];
        $limiter  = new RateLimiter(new MockCache(), static fn (string $key): mixed => $settings[$key] ?? null);

        $this->assertSame(3, $limiter->capacity('feeds'));
        $this->assertSame(120, $limiter->capacity('apiToken'), 'Unset: the default.');

        for ($i = 0; $i < 3; $i++) {
            $this->assertSame(0, $limiter->attempt('feeds', 'token-a'));
        }

        $this->assertGreaterThan(0, $limiter->attempt('feeds', 'token-a'), 'The fourth request waits.');
        $this->assertSame(0, $limiter->attempt('feeds', 'token-b'), 'Subjects are counted apart.');

        $limiter->reset('feeds', 'token-a');
        $this->assertSame(0, $limiter->attempt('feeds', 'token-a'));
    }

    public function testCapacityIsNeverBelowOneAndUnknownLimitsFail(): void
    {
        $limiter = new RateLimiter(new MockCache(), static fn (): mixed => '0');
        $this->assertSame(1, $limiter->capacity('forms'));

        $this->expectException(InvalidArgumentException::class);
        $limiter->attempt('typo', 'x');
    }

    public function testManifestDefaultsMatchTheLimits(): void
    {
        $settings = (new Module())->settings;

        foreach (RateLimiter::LIMITS as $limit => $definition) {
            $key = substr($definition['setting'], strlen('Core.'));
            $this->assertArrayHasKey($key, $settings, $limit);
            $this->assertSame($definition['default'], $settings[$key]['default'], $limit);
        }
    }

    public function testBackOfficeRequestsAreLimitedPerUser(): void
    {
        setting('Core.rateLimitBackend', 2);
        $this->createUser();
        $this->login('ana@example.com');

        $this->send('GET', 'backend/profile')->assertStatus(200);
        $this->send('GET', 'backend/profile')->assertStatus(200);

        $response = $this->send('GET', 'backend/profile');
        $response->assertStatus(429);
        $this->assertGreaterThan(0, (int) $response->response()->getHeaderLine('Retry-After'));
        $this->assertStringContainsString('Too many requests', (string) $response->getBody());

        // Another user has their own counter.
        $this->createUser('joan@example.com');
        $this->login('joan@example.com');
        $this->send('GET', 'backend/profile')->assertStatus(200);
    }

    public function testJsonClientsGetJsonAndGuestsAreCountedPerIp(): void
    {
        setting('Core.rateLimitForms', 1);
        $filter = new RateLimit();

        $request = service('request')->setHeader('Accept', 'application/json');
        $first    = $filter->before($request, ['forms']);
        $response = $filter->before($request, ['forms']);

        $this->assertNull($first);
        $this->assertNotNull($response);
        $this->assertSame(429, $response->getStatusCode());
        $this->assertSame(['error' => lang('Core.errors.tooManyRequests.message')], json_decode((string) $response->getBody(), true));
    }

    public function testRateLimitAliasIsRegistered(): void
    {
        Factories::reset('config');
        $this->assertSame(RateLimit::class, config('Filters')->aliases['ratelimit']);
    }
}
