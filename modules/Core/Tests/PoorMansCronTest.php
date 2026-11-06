<?php

declare(strict_types=1);

namespace Modules\Core\Tests;

use CodeIgniter\Config\Services;
use CodeIgniter\Test\Mock\MockCache;
use Modules\Core\Filters\PoorMansCron;

/**
 * The scheduler fallback for hosts without cron: off by default, at most once a minute, after
 * the response (a shutdown function), and part of the back-office filter chain.
 *
 * @internal
 */
final class PoorMansCronTest extends RbacTestCase
{
    /** @var list<callable> */
    private array $deferred = [];

    protected function setUp(): void
    {
        parent::setUp();
        Services::injectMock('cache', new MockCache());
        PoorMansCron::reset();
        $this->deferred      = [];
        PoorMansCron::$defer = function (callable $callback): void {
            $this->deferred[] = $callback;
        };
    }

    protected function tearDown(): void
    {
        PoorMansCron::$defer = null;
        PoorMansCron::reset();
        Services::resetSingle('cache');
        parent::tearDown();
    }

    private function request(): void
    {
        (new PoorMansCron())->after(service('request'), service('response'));
    }

    public function testOffByDefault(): void
    {
        $this->assertFalse(PoorMansCron::enabled());
        $this->request();
        $this->assertSame([], $this->deferred);
    }

    public function testWhenEnabledItRunsAfterTheResponseAtMostOnceAMinute(): void
    {
        setting('Core.poorMansCron', true);

        $this->request();
        $this->request();
        $this->assertCount(1, $this->deferred, 'Once per request and per minute.');
        $this->assertSame([PoorMansCron::class, 'runAfterResponse'], $this->deferred[0]);

        PoorMansCron::reset();
        $this->assertTrue(PoorMansCron::claimMinute(1_000_000));
        $this->assertFalse(PoorMansCron::claimMinute(1_000_030));
        $this->assertTrue(PoorMansCron::claimMinute(1_000_060));
    }

    public function testItIsPartOfTheBackOfficeChain(): void
    {
        $this->assertContains(PoorMansCron::class, config('Filters')->aliases['backend']);
    }
}
