<?php

declare(strict_types=1);

namespace Modules\Core\Tests;

use CodeIgniter\I18n\Time;
use Modules\Core\Generator\Definition\ModuleDefinition;
use Modules\Core\Generator\Engine;
use Modules\Core\Generator\GeneratorContext;
use Modules\Core\Generator\Plan;
use Modules\Core\Modules\ModuleDiscovery;

/**
 * Helpers of the code generator tests: a context on a temporary modules folder (no database: only
 * `users` exists, the reserved groups), the fixture definitions and a fixed clock, so plans are
 * reproducible (`Fixtures/Generator/expected/`).
 */
trait GeneratorTestTrait
{
    use TempModulesTrait;

    private const FIXTURES = __DIR__ . '/Fixtures/Generator';

    private function definition(string $fixture): ModuleDefinition
    {
        return ModuleDefinition::fromJson((string) file_get_contents(self::FIXTURES . '/' . $fixture . '.json'));
    }

    private function context(): GeneratorContext
    {
        $manifests = ['Auth' => new \Modules\Auth\Config\Module()];

        foreach ((new ModuleDiscovery([$this->tempRoot()]))->discover() as $name => $module) {
            if ($module->manifest !== null) {
                $manifests[$name] = $module->manifest;
            }
        }

        return new GeneratorContext(
            root: $this->tempRoot(),
            manifests: $manifests,
            tableExists: static fn (string $table): bool => $table === 'users',
            groups: ['superadmin', 'admin', 'editor', 'user'],
        );
    }

    private function plan(ModuleDefinition $definition): Plan
    {
        Time::setTestNow('2026-11-20 10:00:00');

        try {
            return (new Engine($this->context()))->plan($definition);
        } finally {
            Time::setTestNow();
        }
    }
}
