<?php

declare(strict_types=1);

namespace Modules\Core\Config;

/**
 * Manifest of the Core module: the kernel every other module depends on.
 */
final class Module extends ModuleManifest
{
    public string $name    = 'Core';
    public string $version = Version::CORE;
    public string $icon    = 'bi bi-cpu';

    /**
     * The Modules screen (session 29) and the module commands: modules.list, modules.toggle,
     * modules.sync, modules.upload, modules.uninstall and modules.demo.
     */
    public array $resources = [
        'modules' => [
            'label'  => 'Core.res.modules',
            'type'   => 'crud',
            'name'   => 'modules',
            'except' => ['show', 'add', 'edit', 'delete', 'export', 'import', 'trash', 'restore', 'purge'],
            'extra'  => [
                'toggle'    => 'Core.perm.toggle',
                'sync'      => 'Core.perm.sync',
                'upload'    => 'Core.perm.upload',
                'uninstall' => 'Core.perm.uninstall',
                'demo'      => 'Core.perm.demo',
            ],
        ],
    ];

    /** upload, uninstall and demo stay with the superadmin (who bypasses the checks). */
    public array $defaultGrants = [
        'admin' => ['modules.list', 'modules.toggle', 'modules.sync'],
    ];
}
