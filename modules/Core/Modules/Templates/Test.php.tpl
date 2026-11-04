<?php

declare(strict_types=1);

namespace Modules\{{Name}}\Tests;

use CodeIgniter\Test\CIUnitTestCase;
use Modules\{{Name}}\Config\Module;

/**
 * @internal
 */
final class {{Name}}ModuleTest extends CIUnitTestCase
{
    public function testManifestIsValid(): void
    {
        $errors = (new \Modules\Core\Modules\ManifestValidator())->validate(new Module(), dirname(__DIR__));

        $this->assertSame([], $errors);
    }
}
