<?php

declare(strict_types=1);

namespace Tests\Support\Scheduler;

use Modules\Core\Cache\CoreCache;
use Modules\Core\Modules\ModuleDiscovery;
use Modules\Core\Registry\DeclarationRegistry;

/**
 * Stores the declarations of the real manifests (what `modules:sync` does in step 6) plus test
 * fixtures: [module, kind ('task'|'notification'), class].
 */
trait DeclaresFixtures
{
    /**
     * @param list<array{string, string, class-string}> $extra
     * @param bool                                      $realTasks Keep the scheduled tasks of the real manifests
     */
    protected function declareFixtures(array $extra = [], bool $realTasks = true): void
    {
        $manifests = [];

        foreach ((new ModuleDiscovery())->discover() as $name => $module) {
            if ($module->isValid() && $module->manifest !== null) {
                $manifests[$name] = $module->manifest;
            }
        }

        $registry     = new DeclarationRegistry();
        $declarations = $registry->build($manifests);

        if (! $realTasks) {
            $declarations = array_filter($declarations, static fn (array $declaration): bool => $declaration['kind'] !== 'task');
        }

        foreach ($extra as [$module, $kind, $class]) {
            $declarations["{$module}|{$kind}|{$class}"] = ['module' => $module, 'kind' => $kind, 'key' => $class, 'definition' => []];
        }

        $registry->store($declarations);
        $this->forgetDeclarations();
    }

    protected function forgetDeclarations(): void
    {
        foreach (['modules', 'settings', 'widgets', 'menus', 'permissions'] as $scope) {
            (new CoreCache())->invalidate($scope);
        }
    }
}
