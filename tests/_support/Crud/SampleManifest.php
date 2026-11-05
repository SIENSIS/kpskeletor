<?php

declare(strict_types=1);

namespace Tests\Support\Crud;

use Modules\Core\Config\ModuleManifest;

/**
 * Manifest of the test-only "Sample" module of the kpcrudy integration tests (session 09): its
 * resources become permissions exactly as a real module's do (`CatalogueBuilder`).
 *
 * - `notes` (crud, no import/trash): `sample-notes.*` plus the extras `publish` (a custom
 *   operation) and `author` (who may see the author field);
 * - `mine` (crud, list/show/edit only): "my notes", the same table limited with `where()`.
 */
final class SampleManifest extends ModuleManifest
{
    public string $name    = 'Sample';
    public string $version = '1.0.0';

    public array $resources = [
        'notes' => [
            'label'  => 'Core.ui.backOffice',
            'type'   => 'crud',
            'except' => ['import', 'trash', 'restore', 'purge'],
            'extra'  => ['publish' => 'Core.ui.backOffice', 'author' => 'Core.ui.backOffice'],
        ],
        'mine' => [
            'label'  => 'Core.ui.backOffice',
            'type'   => 'crud',
            'except' => ['add', 'delete', 'export', 'import', 'trash', 'restore', 'purge'],
        ],
    ];
}
