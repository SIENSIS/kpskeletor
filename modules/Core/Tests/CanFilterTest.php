<?php

declare(strict_types=1);

namespace Modules\Core\Tests;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FilterTestTrait;
use Modules\Core\Filters\Can;

/**
 * The `can:` filter denies by default.
 *
 * @internal
 */
final class CanFilterTest extends CIUnitTestCase
{
    use FilterTestTrait;

    public function testAliasIsRegisteredByTheCoreRegistrar(): void
    {
        $this->assertSame(Can::class, config('Filters')->aliases['can'] ?? null);
    }

    public function testGuestIsForbidden(): void
    {
        $response = (new Can())->before(service('request'), ['news-posts.list']);

        $this->assertNotNull($response);
        $this->assertSame(403, $response->getStatusCode());
    }

    public function testNoPermissionArgumentIsForbidden(): void
    {
        $response = (new Can())->before(service('request'));

        $this->assertNotNull($response);
        $this->assertSame(403, $response->getStatusCode());
    }
}
