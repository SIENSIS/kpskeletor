<?php

declare(strict_types=1);

namespace Modules\Devtools\Generator;

use Modules\Core\Generator\Definition\ModuleDefinition;

/**
 * The wizard's form ↔ definition: the form's names follow the definition's JSON (`module[name]`,
 * `tables[0][columns][1][type]`…), so the posted array is the definition after a few
 * normalisations; the buttons add and remove tables, columns and custom operations.
 */
final class WizardForm
{
    /**
     * The definition posted by the wizard.
     *
     * @param array<string, mixed>  $post
     * @param array<string, string> $installed module => version (for dependencies)
     */
    public static function definition(array $post, array $installed): ModuleDefinition
    {
        $module   = is_array($post['module'] ?? null) ? $post['module'] : [];
        $existing = ($module['mode'] ?? 'new') === 'existing';
        $requires = [];

        foreach (is_array($module['requires'] ?? null) ? $module['requires'] : [] as $dependency) {
            if (is_string($dependency) && isset($installed[$dependency])) {
                $parts                   = explode('.', $installed[$dependency]);
                $requires[$dependency] = '^' . $parts[0] . '.' . ($parts[1] ?? '0');
            }
        }

        $tables = [];

        foreach (is_array($post['tables'] ?? null) ? array_values($post['tables']) : [] as $table) {
            if (! is_array($table)) {
                continue;
            }

            $table['columns'] = array_values(array_filter(is_array($table['columns'] ?? null) ? $table['columns'] : [], static fn ($c): bool => is_array($c)));

            if (is_array($table['crud'] ?? null)) {
                $crud = $table['crud'];

                foreach (['columns', 'fields', 'required', 'search', 'filters', 'uploads', 'richText', 'operations'] as $list) {
                    $crud[$list] = array_values(array_filter(is_array($crud[$list] ?? null) ? $crud[$list] : [], is_string(...)));
                }

                $crud['customOperations'] = array_values(array_filter(is_array($crud['customOperations'] ?? null) ? $crud['customOperations'] : [], static fn ($o): bool => is_array($o)));
                $table['crud']            = $crud;
            }

            $tables[] = $table;
        }

        return ModuleDefinition::fromArray([
            'module' => [
                'name'        => is_string($existing ? ($module['existingName'] ?? null) : ($module['name'] ?? null)) ? ($existing ? $module['existingName'] : $module['name']) : '',
                'existing'    => $existing,
                'description' => $existing ? null : ($module['description'] ?? null),
                'author'      => $existing ? '' : ($module['author'] ?? ''),
                'requires'    => $requires,
            ],
            'tables' => $tables,
        ]);
    }

    /**
     * Applies a structural button (`addTable`, `removeTable:0`, `addColumn:0`, `removeColumn:0:2`,
     * `addOperation:0`, `removeOperation:0:1`) to the definition.
     */
    public static function apply(ModuleDefinition $definition, string $action): ModuleDefinition
    {
        $data  = $definition->toArray();
        $parts = explode(':', $action);
        $t     = (int) ($parts[1] ?? -1);
        $i     = (int) ($parts[2] ?? -1);

        switch ($parts[0]) {
            case 'addTable':
                $prefix           = $definition->lower() === '' ? '' : $definition->lower() . '_';
                $data['tables'][] = ['name' => $prefix, 'columns' => [['name' => 'name', 'type' => 'string']], 'crud' => [], 'grants' => ['admin' => ['*']]];
                break;

            case 'removeTable':
                unset($data['tables'][$t]);
                break;

            case 'addColumn':
                if (isset($data['tables'][$t])) {
                    $data['tables'][$t]['columns'][] = ['name' => '', 'type' => 'string'];
                }
                break;

            case 'removeColumn':
                unset($data['tables'][$t]['columns'][$i]);
                break;

            case 'addOperation':
                if (isset($data['tables'][$t])) {
                    $data['tables'][$t]['crud']['customOperations'][] = ['key' => '', 'icon' => 'bi bi-lightning'];
                }
                break;

            case 'removeOperation':
                unset($data['tables'][$t]['crud']['customOperations'][$i]);
                break;
        }

        foreach ($data['tables'] as &$table) {
            $table['columns'] = array_values($table['columns'] ?? []);

            if (isset($table['crud']['customOperations'])) {
                $table['crud']['customOperations'] = array_values($table['crud']['customOperations']);
            }
        }

        unset($table);
        $data['tables'] = array_values($data['tables']);

        return ModuleDefinition::fromArray($data);
    }
}
