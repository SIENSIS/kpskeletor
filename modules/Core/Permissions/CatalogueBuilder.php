<?php

declare(strict_types=1);

namespace Modules\Core\Permissions;

use Modules\Core\Config\ModuleManifest;
use Modules\Core\Modules\ManifestValidator;

/**
 * Generates the permission catalogue from the manifests (never written by hand):
 *
 * - type 'crud': kpcrudy's operations minus 'except', plus 'extra';
 * - type 'page': 'view' plus 'extra';
 * - every module that declares settings also gets 'settings.<module>' (lower case).
 *
 * Names are "<prefix>.<operation>", the prefix being "<module>-<resource>" or the resource's
 * 'name' override. Duplicate names are reported as errors.
 */
final class CatalogueBuilder
{
    /** @var list<string> */
    private array $errors = [];

    /**
     * @param array<string, ModuleManifest> $manifests name => manifest
     *
     * @return array<string, array{module: string, resource: string, operation: string, name: string, description: string}> name => entry, in manifest order
     */
    public function build(array $manifests): array
    {
        $this->errors = [];
        $catalogue    = [];

        foreach ($manifests as $module => $manifest) {
            foreach ($this->forModule($manifest) as $entry) {
                $name = $entry['name'];

                if (isset($catalogue[$name])) {
                    $this->errors[] = lang('Core.manifest.error', [
                        $module,
                        "\$resources['{$entry['resource']}']",
                        lang('Core.manifest.duplicatePermission', [$name, $catalogue[$name]['module']]),
                    ]);

                    continue;
                }

                $catalogue[$name] = $entry;
            }
        }

        return $catalogue;
    }

    /**
     * Duplicate permission names found by the last build().
     *
     * @return list<string>
     */
    public function errors(): array
    {
        return $this->errors;
    }

    /**
     * Operations of one resource definition.
     *
     * @param array<string, mixed> $definition
     *
     * @return array<string, string> operation => description (language key)
     */
    public static function operations(array $definition): array
    {
        $operations = [];

        if (($definition['type'] ?? null) === 'crud') {
            $except = is_array($definition['except'] ?? null) ? $definition['except'] : [];

            foreach (ManifestValidator::CRUD_OPERATIONS as $operation) {
                if (! in_array($operation, $except, true)) {
                    $operations[$operation] = 'Core.perm.' . $operation;
                }
            }
        } else {
            $operations['view'] = 'Core.perm.view';
        }

        foreach (is_array($definition['extra'] ?? null) ? $definition['extra'] : [] as $operation => $label) {
            $operations[(string) $operation] = (string) $label;
        }

        return $operations;
    }

    /**
     * @return list<array{module: string, resource: string, operation: string, name: string, description: string}>
     */
    private function forModule(ModuleManifest $manifest): array
    {
        $entries = [];

        foreach ($manifest->resources as $resource => $definition) {
            $prefix = $manifest->permissionPrefix((string) $resource);

            foreach (self::operations($definition) as $operation => $description) {
                $entries[] = [
                    'module'      => $manifest->name,
                    'resource'    => (string) $resource,
                    'operation'   => $operation,
                    'name'        => $prefix . '.' . $operation,
                    'description' => $description,
                ];
            }
        }

        if ($manifest->settings !== []) {
            $operation = strtolower($manifest->name);
            $entries[] = [
                'module'      => $manifest->name,
                'resource'    => 'settings',
                'operation'   => $operation,
                'name'        => 'settings.' . $operation,
                'description' => 'Core.perm.settings',
            ];
        }

        return $entries;
    }
}
