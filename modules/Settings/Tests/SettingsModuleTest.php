<?php

declare(strict_types=1);

namespace Modules\Settings\Tests;

use CodeIgniter\Test\CIUnitTestCase;
use Modules\Settings\Config\Module;

/**
 * @internal
 */
final class SettingsModuleTest extends CIUnitTestCase
{
    public function testManifestIsValid(): void
    {
        $errors = (new \Modules\Core\Modules\ManifestValidator())->validate(new Module(), dirname(__DIR__));

        $this->assertSame([], $errors);
    }
}
