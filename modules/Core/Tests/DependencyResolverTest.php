<?php

declare(strict_types=1);

namespace Modules\Core\Tests;

use CodeIgniter\Test\CIUnitTestCase;
use Modules\Core\Config\ModuleManifest;
use Modules\Core\Modules\DependencyResolver;

/**
 * @internal
 */
final class DependencyResolverTest extends CIUnitTestCase
{
    /**
     * @param array<string, string> $requires
     */
    private function manifest(string $name, string $version, array $requires = []): ModuleManifest
    {
        $manifest           = new class () extends ModuleManifest {};
        $manifest->name     = $name;
        $manifest->version  = $version;
        $manifest->requires = $requires;

        return $manifest;
    }

    protected function setUp(): void
    {
        parent::setUp();
        service('language')->setLocale('en');
    }

    public function testCoreFirstThenDependencies(): void
    {
        $resolver = new DependencyResolver();
        $order    = $resolver->order([
            'Blog'  => $this->manifest('Blog', '1.0.0', ['Core' => '^1.0', 'Users' => '^1.0']),
            'Users' => $this->manifest('Users', '1.2.0', ['Core' => '^1.0']),
            'Core'  => $this->manifest('Core', '1.0.0'),
            'Alpha' => $this->manifest('Alpha', '1.0.0'),
        ]);

        $this->assertSame(['Core', 'Alpha', 'Users', 'Blog'], $order);
        $this->assertSame([], $resolver->errors());
    }

    public function testMissingAndIncompatibleDependencies(): void
    {
        $resolver = new DependencyResolver();
        $resolver->order([
            'Core' => $this->manifest('Core', '0.1.0'),
            'Blog' => $this->manifest('Blog', '1.0.0', ['Core' => '^1.0', 'Users' => '^1.0']),
        ]);

        $errors = implode("\n", $resolver->errors());
        $this->assertStringContainsString("Blog: \$requires['Users'] requires the module Users, which is not present", $errors);
        $this->assertStringContainsString("Blog: \$requires['Core'] requires Core ^1.0, but version 0.1.0 is present", $errors);
    }

    public function testCircularDependency(): void
    {
        $resolver = new DependencyResolver();
        $resolver->order([
            'Core' => $this->manifest('Core', '1.0.0'),
            'A'    => $this->manifest('A', '1.0.0', ['B' => '*']),
            'B'    => $this->manifest('B', '1.0.0', ['A' => '*']),
        ]);

        $this->assertStringContainsString('circular dependency', implode("\n", $resolver->errors()));
    }
}
