<?php

declare(strict_types=1);

namespace Modules\Settings\Tests;

use Modules\Auth\Tests\AuthTestCase;
use Modules\Core\Cache\CoreCache;
use Modules\Core\Modules\ModuleDiscovery;
use Modules\Core\Registry\DeclarationRegistry;

/**
 * Base of the Settings tests: the Auth fixture (migrated database, catalogue, grants, requests
 * with session and CSRF) plus the manifests' declarations, so the settings registry knows every
 * declared setting as after `modules:sync`.
 */
abstract class SettingsTestCase extends AuthTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $manifests = [];

        foreach ((new ModuleDiscovery())->discover() as $name => $module) {
            if ($module->isValid() && $module->manifest !== null) {
                $manifests[$name] = $module->manifest;
            }
        }

        $registry = new DeclarationRegistry();
        $registry->store($registry->build($manifests));
        (new CoreCache())->invalidate('settings');
    }


    /**
     * @return list<array<string, mixed>>
     */
    protected function entries(string $action): array
    {
        return db_connect()->table('activity_log')->where('action', $action)->orderBy('id')->get()->getResultArray();
    }

    protected function storedCount(string $class, string $key, ?string $context = null): int
    {
        return db_connect()->table('settings')->where('class', $class)->where('key', $key)->where('context', $context)->countAllResults();
    }
}
