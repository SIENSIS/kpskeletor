<?php

declare(strict_types=1);

namespace Modules\Core\Generator;

use Modules\Core\Generator\Definition\ColumnDefinition;
use Modules\Core\Generator\Definition\CrudDefinition;
use Modules\Core\Generator\Definition\MenuDefinition;
use Modules\Core\Generator\Definition\ModuleDefinition;
use Modules\Core\Generator\Definition\TableDefinition;
use Modules\Core\Modules\CrudScaffold;
use Modules\Core\Modules\ModuleGenerator;

/**
 * Checks a definition before anything is generated. Errors are keyed by the path of the value
 * (`module.name`, `tables.0.columns.2.default`, …) so the wizard shows each one next to its field;
 * the messages are `Core.generator.errors.<code>`.
 */
final class DefinitionValidator
{
    private const TABLE_PATTERN  = '/^[a-z][a-z0-9_]{1,59}$/';
    private const COLUMN_PATTERN = '/^[a-z][a-z0-9_]{0,59}$/';
    private const KEY_PATTERN    = '/^[a-z][a-z0-9_]{0,39}$/';
    private const OPTION_PATTERN = '/^[A-Za-z0-9_-]{1,40}$/';

    /** Longest indexed VARCHAR portable to MariaDB with utf8mb4. */
    private const MAX_INDEXED_LENGTH = 190;

    /** @var array<string, string> */
    private array $errors = [];

    public function __construct(private readonly GeneratorContext $context = new GeneratorContext())
    {
    }

    /**
     * @return array<string, string> path => message (empty when the definition is valid)
     */
    public function validate(ModuleDefinition $definition): array
    {
        $this->errors = [];
        $this->module($definition);

        $names     = [];
        $keys      = $this->existingResourceKeys($definition);
        $available = [];

        foreach ($definition->tables as $i => $table) {
            $path = 'tables.' . $i;
            $this->table($definition, $table, $path, $names, $keys, $available);
            $names[]     = $table->name;
            $keys[]      = $table->key($definition->name);
            $available[] = $table->name;
        }

        if ($definition->existing && $definition->tables === []) {
            $this->error('tables', 'noTables');
        }

        return $this->errors;
    }

    private function module(ModuleDefinition $definition): void
    {
        $name = $definition->name;

        if ($definition->existing) {
            if (preg_match('/^[A-Z][A-Za-z0-9]*$/', $name) !== 1 || ! is_file($this->context->modulePath($name) . '/Config/Module.php')) {
                $this->error('module.name', 'moduleMissing', [$name]);
            }
        } else {
            $error = (new ModuleGenerator($this->context->root))->nameError($name);

            if ($error !== null) {
                $this->errors['module.name'] = $error;
            }
        }

        foreach ($definition->requires as $module => $constraint) {
            if ($this->context->manifest($module) === null) {
                $this->error('module.requires', 'unknownModule', [$module]);
            } elseif (preg_match('/^(\*|[\^~]?\d+(\.\d+){0,2})$/', $constraint) !== 1) {
                $this->error('module.requires', 'constraint', [$module, $constraint]);
            }
        }

        if (mb_strlen($definition->author) > 120) {
            $this->error('module.author', 'tooLong', [120]);
        }
    }

    /**
     * @param list<string> $names     tables of the definition before this one
     * @param list<string> $keys      resource keys already taken
     * @param list<string> $available tables of the definition this one may reference
     */
    private function table(ModuleDefinition $definition, TableDefinition $table, string $path, array $names, array $keys, array $available): void
    {
        $lower = $definition->lower();

        if (preg_match(self::TABLE_PATTERN, $table->name) !== 1) {
            $this->error($path . '.name', 'tableName', [$table->name]);
        } elseif (! str_starts_with($table->name, $lower . '_')) {
            $this->error($path . '.name', 'tablePrefix', [$lower . '_']);
        } elseif (in_array($table->name, $names, true)) {
            $this->error($path . '.name', 'tableTwice', [$table->name]);
        } elseif ($this->context->tableExists($table->name)) {
            $this->error($path . '.name', 'tableExists', [$table->name]);
        } elseif ($table->translatedColumns() !== [] && $this->context->tableExists($table->translationTable())) {
            $this->error($path . '.name', 'tableExists', [$table->translationTable()]);
        } elseif (in_array($table->key($definition->name), $keys, true) || $table->key($definition->name) === 'main' && ! $definition->existing) {
            $this->error($path . '.name', 'resourceTaken', [$table->resource($definition->name)]);
        }

        if ($table->columns === []) {
            $this->error($path . '.columns', 'noColumns');
        }

        $columnNames = [];

        foreach ($table->columns as $c => $column) {
            $this->column($table, $column, $path . '.columns.' . $c, $columnNames, $available);
            $columnNames[] = $column->name;
        }

        if ($table->crud->enabled) {
            $this->crud($table, $path . '.crud');
        }

        if ($table->api->enabled && ! $table->crud->enabled && ! $table->api->public) {
            $this->error($path . '.api.enabled', 'apiNeedsCrud');
        }

        $this->menu($definition, $table, $path . '.menu');
        $this->grants($definition, $table, $path . '.grants');
    }

    /**
     * @param list<string> $before    columns before this one
     * @param list<string> $available tables of the definition before this one
     */
    private function column(TableDefinition $table, ColumnDefinition $column, string $path, array $before, array $available): void
    {
        if (preg_match(self::COLUMN_PATTERN, $column->name) !== 1) {
            $this->error($path . '.name', 'columnName', [$column->name]);

            return;
        }

        if (in_array($column->name, ColumnDefinition::RESERVED, true)) {
            $this->error($path . '.name', 'columnReserved', [$column->name]);
        } elseif (in_array($column->name, $before, true)) {
            $this->error($path . '.name', 'columnTwice', [$column->name]);
        } elseif ($column->translated && $column->name === $table->translationKey()) {
            $this->error($path . '.name', 'columnReserved', [$column->name]);
        }

        if (! in_array($column->type, ColumnDefinition::TYPES, true)) {
            $this->error($path . '.type', 'type', [$column->type]);

            return;
        }

        $indexed = $column->unique || $column->index || $column->foreign !== null;

        if (in_array($column->type, ['string', 'enum'], true)) {
            if ($column->length() < 1 || $column->length() > 255) {
                $this->error($path . '.length', 'length', [255]);
            } elseif ($indexed && $column->length() > self::MAX_INDEXED_LENGTH) {
                $this->error($path . '.length', 'indexedLength', [self::MAX_INDEXED_LENGTH]);
            }
        }

        if (in_array($column->type, ['text', 'json'], true) && $indexed) {
            $this->error($path . '.unique', 'textIndex');
        }

        if ($column->type === 'decimal' && ($column->precision < 1 || $column->precision > 30 || $column->scale < 0 || $column->scale > $column->precision)) {
            $this->error($path . '.precision', 'decimal');
        }

        if ($column->type === 'enum') {
            if ($column->options === []) {
                $this->error($path . '.options', 'enumOptions');
            }

            foreach ($column->options as $option) {
                if (preg_match(self::OPTION_PATTERN, $option) !== 1 || strlen($option) > $column->length()) {
                    $this->error($path . '.options', 'enumOption', [$option]);
                }
            }
        }

        if ($column->default !== null) {
            $this->defaultValue($column, $path . '.default');
        }

        if ($column->translated) {
            if (! in_array($column->type, ['string', 'text'], true)) {
                $this->error($path . '.translated', 'translatedType');
            }

            if ($indexed) {
                $this->error($path . '.translated', 'translatedIndex');
            }
        }

        if ($column->faker !== null && ! in_array($column->faker, DemoValues::GENERATORS, true)) {
            $this->error($path . '.faker', 'faker', [$column->faker]);
        }

        if ($column->foreign !== null) {
            $this->foreign($column, $path . '.foreign', $available);
        }
    }

    private function defaultValue(ColumnDefinition $column, string $path): void
    {
        $value = $column->default ?? '';
        $valid = match ($column->type) {
            'integer', 'bigint' => preg_match('/^-?\d+$/', $value) === 1,
            'decimal'           => is_numeric($value),
            'boolean'           => in_array($value, ['0', '1'], true),
            'date'              => \DateTime::createFromFormat('!Y-m-d', $value) !== false && \DateTime::createFromFormat('!Y-m-d', $value)->format('Y-m-d') === $value,
            'datetime'          => \DateTime::createFromFormat('Y-m-d H:i:s', $value) !== false,
            'enum'              => in_array($value, $column->options, true),
            'string'            => mb_strlen($value) <= $column->length(),
            default             => false,
        };

        if (! $valid) {
            $this->error($path, in_array($column->type, ['text', 'json'], true) ? 'noDefault' : 'default', [$value]);
        }
    }

    /**
     * @param list<string> $available
     */
    private function foreign(ColumnDefinition $column, string $path, array $available): void
    {
        $foreign = $column->foreign ?? [];

        if (! in_array($column->type, ['integer', 'bigint'], true)) {
            $this->error($path . '.table', 'foreignType');
        }

        if (preg_match(self::TABLE_PATTERN, $foreign['table']) !== 1 || ! (in_array($foreign['table'], $available, true) || $this->context->tableExists($foreign['table']))) {
            $this->error($path . '.table', 'foreignTable', [$foreign['table']]);
        }

        if ($foreign['column'] !== 'id') {
            $this->error($path . '.table', 'foreignColumn');
        }

        if (! in_array($foreign['onDelete'], ColumnDefinition::ON_DELETE, true)) {
            $this->error($path . '.onDelete', 'onDelete', [$foreign['onDelete']]);
        } elseif ($foreign['onDelete'] === 'SET NULL' && ! $column->nullable) {
            $this->error($path . '.onDelete', 'setNullNotNull', [$column->name]);
        }
    }

    private function crud(TableDefinition $table, string $path): void
    {
        $crud   = $table->crud;
        $fields = $table->fieldNames();

        foreach (['columns', 'fields', 'required', 'search', 'filters', 'uploads', 'richText'] as $list) {
            foreach ($crud->{$list} as $name) {
                if (! in_array($name, $fields, true)) {
                    $this->error($path . '.' . $list, 'unknownField', [$name]);
                }
            }
        }

        foreach ($crud->required as $name) {
            if ($crud->fields !== [] && ! in_array($name, $crud->fields, true)) {
                $this->error($path . '.required', 'requiredNotField', [$name]);
            }
        }

        foreach ($crud->uploads as $name) {
            $column = $table->column($name);

            if ($column !== null && ($column->type !== 'string' || $column->translated)) {
                $this->error($path . '.uploads', 'uploadType', [$name]);
            }
        }

        foreach ($crud->richText as $name) {
            if ($table->column($name)?->type !== 'text') {
                $this->error($path . '.richText', 'richTextType', [$name]);
            }
        }

        if ($crud->offers('trash') && ! $table->softDeletes) {
            $this->error($path . '.operations', 'trashNeedsSoftDeletes');
        }

        $standard = [...CrudDefinition::OPERATIONS, 'restore', 'purge', 'bulk', 'search', 'filters', 'view'];
        $keys     = [];

        foreach ($crud->customOperations as $o => $operation) {
            if (preg_match(self::KEY_PATTERN, $operation['key']) !== 1 || in_array($operation['key'], $standard, true)) {
                $this->error($path . '.customOperations.' . $o . '.key', 'operationKey', [$operation['key']]);
            } elseif (in_array($operation['key'], $keys, true)) {
                $this->error($path . '.customOperations.' . $o . '.key', 'operationTwice', [$operation['key']]);
            }

            $keys[] = $operation['key'];
        }

        foreach ($crud->hidden as $field => $permission) {
            if (! in_array($field, $fields, true)) {
                $this->error($path . '.hidden', 'unknownField', [$field]);
            }

            if (preg_match(self::KEY_PATTERN, $permission) !== 1 || in_array($permission, $standard, true)) {
                $this->error($path . '.hidden', 'operationKey', [$permission]);
            }
        }

        if (! in_array($crud->recordRule, CrudDefinition::RECORD_RULES, true)) {
            $this->error($path . '.recordRule', 'recordRule', [$crud->recordRule]);
        } elseif ($crud->recordRule === 'column') {
            $column = $crud->ruleColumn === null ? null : $table->column($crud->ruleColumn);

            if ($column === null || ! in_array($column->type, ['integer', 'bigint'], true) || $column->translated) {
                $this->error($path . '.ruleColumn', 'ruleColumn', [(string) $crud->ruleColumn]);
            }
        } elseif ($crud->recordRule === 'own' && ($owner = $table->column(CrudDefinition::OWNER_COLUMN)) !== null && ! in_array($owner->type, ['integer', 'bigint'], true)) {
            $this->error($path . '.recordRule', 'ruleColumn', [CrudDefinition::OWNER_COLUMN]);
        }
    }

    private function menu(ModuleDefinition $definition, TableDefinition $table, string $path): void
    {
        $menu = $table->menu;

        if (! $menu->enabled || ! $table->crud->enabled) {
            return;
        }

        if (! in_array($menu->zone, MenuDefinition::ZONES, true)) {
            $this->error($path . '.zone', 'zone', [$menu->zone]);

            return;
        }

        if (preg_match('/^bi bi-[a-z0-9-]+$/', $menu->icon) !== 1) {
            $this->error($path . '.icon', 'icon', [$menu->icon]);
        }

        if ($menu->zone === 'settings') {
            if (! in_array($menu->parent, MenuDefinition::SETTINGS_GROUPS, true)) {
                $this->error($path . '.parent', 'settingsGroup');
            }

            return;
        }

        if ($menu->parent !== null && ! in_array($menu->parent, $this->resourceKeys($definition), true)) {
            $this->error($path . '.parent', 'parent', [$menu->parent]);
        }
    }

    private function grants(ModuleDefinition $definition, TableDefinition $table, string $path): void
    {
        $operations = $table->operations();
        $groups     = $this->context->groups();

        foreach ($table->grants as $group => $granted) {
            if (! in_array($group, $groups, true)) {
                $this->error($path . '.' . $group, 'group', [$group]);

                continue;
            }

            foreach ($granted as $operation) {
                if ($operation !== '*' && ! in_array($operation, $operations, true)) {
                    $this->error($path . '.' . $group, 'grant', [$table->resource($definition->name) . '.' . $operation]);
                }
            }
        }
    }

    /**
     * Resource keys an entry may hang from: the module's own (existing manifest, or `main` of a
     * new module) and the definition's tables.
     *
     * @return list<string>
     */
    private function resourceKeys(ModuleDefinition $definition): array
    {
        $keys = $this->existingResourceKeys($definition);

        foreach ($definition->tables as $table) {
            $keys[] = $table->key($definition->name);
        }

        return $keys;
    }

    /**
     * @return list<string>
     */
    private function existingResourceKeys(ModuleDefinition $definition): array
    {
        if (! $definition->existing) {
            return ['main'];
        }

        $manifest = $this->context->manifest($definition->name);

        return $manifest === null ? [] : array_map(strval(...), array_keys($manifest->resources));
    }

    /**
     * @param list<int|string> $args
     */
    private function error(string $path, string $code, array $args = []): void
    {
        $this->errors[$path] ??= lang('Core.generator.errors.' . $code, $args);
    }

    /**
     * Problems with a table name alone (the console and the wizard's live check).
     */
    public static function tableNameError(string $module, string $table): ?string
    {
        if (CrudScaffold::tableError($table) !== null || preg_match(self::TABLE_PATTERN, $table) !== 1) {
            return lang('Core.generator.errors.tableName', [$table]);
        }

        return str_starts_with($table, strtolower($module) . '_') ? null : lang('Core.generator.errors.tablePrefix', [strtolower($module) . '_']);
    }
}
