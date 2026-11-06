<?php

declare(strict_types=1);

namespace Modules\Activity\Tests;

use CodeIgniter\Test\CIUnitTestCase;
use Modules\Activity\Config\Module;

/**
 * @internal
 */
final class ActivityModuleTest extends CIUnitTestCase
{
    public function testManifestIsValid(): void
    {
        $errors = (new \Modules\Core\Modules\ManifestValidator())->validate(new Module(), dirname(__DIR__));

        $this->assertSame([], $errors);
    }
}
