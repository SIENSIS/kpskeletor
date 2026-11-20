<?php

declare(strict_types=1);

namespace Modules\Core\Generator\Definition;

use Modules\Core\Modules\CrudScaffold;

/**
 * A table of a generated module with its demo rows, CRUD, API, menu entry and default grants.
 * The manifest resource is `<module>-<key>` (`CrudScaffold::resourceKey()`: the table name
 * without the `<module>_` prefix and underscores), so `library_books` in Library is
 * `library-books` and its permissions `library-books.<operation>`.
 */
final class TableDefinition
{
    /**
     * @param list<ColumnDefinition>      $columns
     * @param array<string, list<string>> $grants group => operations (`*` = every one)
     */
    public function __construct(
        public readonly string $name,
        public readonly Texts $singular,
        public readonly Texts $plural,
        public readonly array $columns,
        public readonly bool $timestamps = true,
        public readonly bool $softDeletes = false,
        public readonly int $demoRows = 5,
        public readonly CrudDefinition $crud = new CrudDefinition(),
        public readonly ApiDefinition $api = new ApiDefinition(),
        public readonly MenuDefinition $menu = new MenuDefinition(),
        public readonly array $grants = ['admin' => ['*']],
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $name    = is_string($data['name'] ?? null) ? strtolower(trim($data['name'])) : '';
        $columns = [];

        foreach (is_array($data['columns'] ?? null) ? $data['columns'] : [] as $column) {
            if (is_array($column) && is_string($column['name'] ?? null) && trim($column['name']) !== '') {
                $columns[] = ColumnDefinition::fromArray($column);
            }
        }

        $grants = ['admin' => ['*']];

        if (is_array($data['grants'] ?? null)) {
            $grants = [];

            foreach ($data['grants'] as $group => $operations) {
                $operations = CrudDefinition::names($operations);

                if (is_string($group) && $operations !== []) {
                    $grants[$group] = $operations;
                }
            }
        }

        $labels = is_array($data['labels'] ?? null) ? $data['labels'] : [];
        $words  = Texts::humanise((string) preg_replace('/^[a-z0-9]+_/', '', $name));

        $softDeletes = ColumnDefinition::bool($data['softDeletes'] ?? false);
        $crud        = is_array($data['crud'] ?? null) ? $data['crud'] : [];

        if (! isset($crud['operations']) && ! $softDeletes) {
            $crud['operations'] = array_values(array_diff(CrudDefinition::OPERATIONS, ['trash']));
        }

        return new self(
            name: $name,
            singular: Texts::from($labels['singular'] ?? null, $words),
            plural: Texts::from($labels['plural'] ?? null, $words),
            columns: $columns,
            timestamps: ! array_key_exists('timestamps', $data) || ColumnDefinition::bool($data['timestamps']),
            softDeletes: $softDeletes,
            demoRows: max(0, min(200, ColumnDefinition::int($data['demoRows'] ?? null) ?? 5)),
            crud: CrudDefinition::fromArray($crud),
            api: ApiDefinition::fromArray(is_array($data['api'] ?? null) || is_bool($data['api'] ?? null) ? $data['api'] : null),
            menu: MenuDefinition::fromArray(is_array($data['menu'] ?? null) ? $data['menu'] : null),
            grants: $grants,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'name'        => $this->name,
            'labels'      => ['singular' => $this->singular->toArray(), 'plural' => $this->plural->toArray()],
            'timestamps'  => $this->timestamps,
            'softDeletes' => $this->softDeletes,
            'demoRows'    => $this->demoRows,
            'columns'     => array_map(static fn (ColumnDefinition $c): array => $c->toArray(), $this->columns),
            'crud'        => $this->crud->toArray(),
            'api'         => $this->api->toArray(),
            'menu'        => $this->menu->toArray(),
            'grants'      => $this->grants,
        ];
    }

    public function key(string $module): string
    {
        return CrudScaffold::resourceKey($module, $this->name);
    }

    public function resource(string $module): string
    {
        return CrudScaffold::resource($module, $this->name);
    }

    /** `library_books` → `LibraryBooks` (migration and class names). */
    public function studly(): string
    {
        return str_replace(' ', '', ucwords(str_replace('_', ' ', $this->name)));
    }

    public function translationTable(): string
    {
        return $this->name . '_translations';
    }

    /** `library_books` → `library_book_id`. */
    public function translationKey(): string
    {
        $singular = (string) preg_replace(['/ies$/', '/(?<!s)s$/'], ['y', ''], $this->name);

        return $singular . '_id';
    }

    public function column(string $name): ?ColumnDefinition
    {
        foreach ($this->columns as $column) {
            if ($column->name === $name) {
                return $column;
            }
        }

        return null;
    }

    /**
     * Columns stored in the table itself (not translated).
     *
     * @return list<ColumnDefinition>
     */
    public function plainColumns(): array
    {
        return array_values(array_filter($this->columns, static fn (ColumnDefinition $c): bool => ! $c->translated));
    }

    /**
     * @return list<ColumnDefinition>
     */
    public function translatedColumns(): array
    {
        return array_values(array_filter($this->columns, static fn (ColumnDefinition $c): bool => $c->translated));
    }

    /**
     * Names of every column of the CRUD and API, the owner column of the `own` rule included.
     *
     * @return list<string>
     */
    public function fieldNames(): array
    {
        $names = array_map(static fn (ColumnDefinition $c): string => $c->name, $this->columns);

        if ($this->needsOwnerColumn()) {
            $names[] = CrudDefinition::OWNER_COLUMN;
        }

        return $names;
    }

    /** The `own` rule needs `created_by`, added when the definition does not declare it. */
    public function needsOwnerColumn(): bool
    {
        return $this->crud->recordRule === 'own' && $this->column(CrudDefinition::OWNER_COLUMN) === null;
    }

    /**
     * Extra operations of the resource: custom operations and hidden-field permissions.
     *
     * @return array<string, Texts> operation => label
     */
    public function extras(): array
    {
        $extras = [];

        foreach ($this->crud->customOperations as $operation) {
            $extras[$operation['key']] = $operation['label'];
        }

        foreach ($this->crud->hidden as $field => $permission) {
            $extras[$permission] ??= Texts::from(null, Texts::humanise($permission));
        }

        if ($this->crud->enabled && $this->crud->ownerColumn() !== null) {
            $extras['all'] ??= Texts::from(['ca' => 'Tots els registres', 'es' => 'Todos los registros', 'en' => 'Every record'], 'Every record');
        }

        return $extras;
    }

    /**
     * Manifest operations the resource leaves out (`except`).
     *
     * @return list<string>
     */
    public function except(): array
    {
        $except = array_values(array_diff(['show', 'add', 'edit', 'delete', 'export', 'import'], $this->crud->operations));

        if (! $this->softDeletes || ! $this->crud->offers('trash')) {
            $except = [...$except, 'trash', 'restore', 'purge'];
        } elseif ($this->crud->readOnly) {
            $except = [...$except, 'restore', 'purge'];
        }

        return $except;
    }

    /**
     * Operations of the resource (catalogue order), extras included.
     *
     * @return list<string>
     */
    public function operations(): array
    {
        $standard = array_values(array_diff(['list', 'show', 'add', 'edit', 'delete', 'export', 'import', 'trash', 'restore', 'purge'], $this->except()));

        return [...$standard, ...array_keys($this->extras())];
    }

    /**
     * The permission names granted to a group by default (`*` expanded to `<resource>.*`).
     *
     * @return list<string>
     */
    public function grantsFor(string $module, string $group): array
    {
        $resource = $this->resource($module);
        $grants   = [];

        foreach ($this->grants[$group] ?? [] as $operation) {
            $grants[] = $resource . '.' . $operation;
        }

        return $grants;
    }
}
