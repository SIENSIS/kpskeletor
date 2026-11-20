<?php

declare(strict_types=1);

namespace Modules\Core\Generator;

use Modules\Core\Generator\Definition\ColumnDefinition;
use Modules\Core\Generator\Definition\CrudDefinition;
use Modules\Core\Generator\Definition\ModuleDefinition;
use Modules\Core\Generator\Definition\TableDefinition;
use Modules\Core\Generator\Definition\Texts;

/**
 * The code of one table of a definition: its migrations, CRUD and API controllers, file-picker
 * handler, tests, and what it adds to the module's manifest, routes, language files, demo seeder
 * and `CLAUDE.md`. Every file follows `CLAUDE.md` (see `docs/devtools.md`, "What it generates").
 */
final class TableCode
{
    private readonly string $module;

    private readonly string $lower;

    private readonly string $key;

    private readonly string $resource;

    private readonly string $class;

    private readonly DemoValues $demo;

    public function __construct(
        private readonly ModuleDefinition $definition,
        private readonly TableDefinition $table,
        private readonly GeneratorContext $context,
    ) {
        $this->module   = $definition->name;
        $this->lower    = $definition->lower();
        $this->key      = $table->key($this->module);
        $this->resource = $table->resource($this->module);
        $this->class    = ucfirst($this->key);
        $this->demo     = new DemoValues($table->name);
    }

    /**
     * The table's English plural for doc comments: one line, no comment terminator (labels are
     * free text).
     */
    private function docText(): string
    {
        return trim((string) preg_replace('/\s+/', ' ', str_replace(['*/', '/*'], ['* /', '/ *'], $this->table->plural->english())));
    }

    // ------------------------------------------------------------------ effective CRUD lists

    /**
     * Form fields: the definition's, or every column but the owner column.
     *
     * @return list<string>
     */
    public function formFields(): array
    {
        $fields = $this->table->crud->fields !== [] ? $this->table->crud->fields : $this->table->fieldNames();

        return array_values(array_filter($fields, fn (string $f): bool => $f !== $this->ownerOnInsert()));
    }

    /**
     * @return list<string>
     */
    public function listColumns(): array
    {
        if ($this->table->crud->columns !== []) {
            return $this->table->crud->columns;
        }

        $columns = array_filter($this->formFields(), fn (string $f): bool => ! in_array($this->table->column($f)?->type, ['text', 'json'], true));

        return array_slice(array_values($columns), 0, 5);
    }

    /**
     * @return list<string>
     */
    public function required(): array
    {
        if ($this->table->crud->required !== []) {
            return $this->table->crud->required;
        }

        $required = [];

        foreach ($this->formFields() as $field) {
            $column = $this->table->column($field);

            if ($column !== null && ! $column->nullable && $column->default === null && $column->type !== 'boolean' && ! in_array($field, $this->table->crud->uploads, true)) {
                $required[] = $field;
            }
        }

        return $required;
    }

    /**
     * @return list<string>
     */
    public function searchFields(): array
    {
        if ($this->table->crud->search !== []) {
            return $this->table->crud->search;
        }

        $fields = array_filter($this->listColumns(), fn (string $f): bool => $this->table->column($f)?->type === 'string' && ! in_array($f, $this->table->crud->uploads, true));

        return array_slice(array_values($fields), 0, 3);
    }

    /**
     * @return list<string>
     */
    public function filterFields(): array
    {
        if ($this->table->crud->filters !== []) {
            return $this->table->crud->filters;
        }

        return array_values(array_filter($this->formFields(), function (string $f): bool {
            $column = $this->table->column($f);

            return $column !== null && ! $column->translated && ($column->type === 'enum' || $column->type === 'boolean' || $column->foreign !== null);
        }));
    }

    /** The column set to the user's id on insert (`own` / `column` rules), or null. */
    private function ownerOnInsert(): ?string
    {
        return $this->table->crud->recordRule === 'own' ? CrudDefinition::OWNER_COLUMN : null;
    }

    private function ruleColumn(): ?string
    {
        return $this->table->crud->ownerColumn();
    }

    /**
     * Extra operations of the resource (custom operations, hidden-field permissions, `all`).
     *
     * @return array<string, Texts>
     */
    public function extras(): array
    {
        return $this->table->extras();
    }

    // ------------------------------------------------------------------ manifest, routes, texts

    /**
     * The manifest resource entry.
     *
     * @return array<string, mixed>
     */
    public function resourceEntry(): array
    {
        $entry = ['label' => $this->module . '.res.' . $this->key, 'type' => 'crud'];
        $except = $this->table->except();

        if ($except !== []) {
            $entry['except'] = $except;
        }

        $extras = [];

        foreach (array_keys($this->extras()) as $extra) {
            $extras[$extra] = $this->module . '.perm.' . $this->key . '.' . $extra;
        }

        if ($extras !== []) {
            $entry['extra'] = $extras;
        }

        return $entry;
    }

    /**
     * Default grants: group => permission names (`*` = `<resource>.*`).
     *
     * @return array<string, list<string>>
     */
    public function grants(): array
    {
        $grants = [];

        foreach (array_keys($this->table->grants) as $group) {
            $grants[$group] = $this->table->grantsFor($this->module, $group);
        }

        return $grants;
    }

    /**
     * The menu entry, or null.
     *
     * @return array<string, mixed>|null
     */
    public function menuEntry(): ?array
    {
        $menu = $this->table->menu;

        if (! $menu->enabled || ! $this->table->crud->enabled) {
            return null;
        }

        $entry = ['resource' => $this->key, 'label' => $this->module . '.menu.' . $this->key, 'icon' => $menu->icon, 'order' => $menu->order];

        if ($menu->parent !== null) {
            $entry['parent'] = $menu->parent;
        }

        if ($menu->zone === 'settings') {
            $entry['zone'] = 'settings';
        }

        return $entry;
    }

    /**
     * The `$api` entry (key => entry), or [].
     *
     * @return array<string, array{path: string, handler: string}>
     */
    public function apiEntry(): array
    {
        if (! $this->table->api->enabled) {
            return [];
        }

        $key = $this->table->api->public ? 'public-' . $this->key : $this->key;

        return [$key => ['path' => $this->apiPath(), 'handler' => $this->apiHandler()]];
    }

    public function apiPath(): string
    {
        return ($this->table->api->public ? 'public/' : '') . $this->lower . '/' . $this->key;
    }

    private function apiHandler(): string
    {
        return '\\Modules\\' . $this->module . '\\Controllers\\Api\\' . $this->apiClass() . '::index';
    }

    private function apiClass(): string
    {
        return ($this->table->api->public ? 'Public' : '') . $this->class;
    }

    /**
     * Route lines appended to `Config/Routes.php`.
     */
    public function routes(): string
    {
        $lines = [];

        if ($this->table->crud->enabled) {
            $lines[] = '\\Modules\\Core\\Crud\\CrudFactory::routes($routes, ' . PhpExport::string($this->resource) . ', backend_path(' . PhpExport::string($this->lower . '/' . $this->key) . '), ' . PhpExport::string('\\Modules\\' . $this->module . '\\Controllers\\' . $this->class . '::index') . ');';
        }

        if ($this->table->api->enabled) {
            $path    = PhpExport::string('api/v1/' . $this->apiPath());
            $handler = PhpExport::string($this->apiHandler());

            if ($this->table->api->public) {
                $any     = PhpExport::string('api/v1/' . $this->apiPath() . '/(:any)');
                $lines[] = '$routes->get(' . $path . ', ' . $handler . ", ['filter' => 'kpapy:public', 'kpapy' => true]);";
                $lines[] = '$routes->get(' . $any . ', ' . $handler . ", ['filter' => 'kpapy:public']);";
                $lines[] = '$routes->options(' . $path . ", static fn () => service('response')->setStatusCode(204), ['filter' => 'kpapy:public']);";
                $lines[] = '$routes->options(' . $any . ", static fn () => service('response')->setStatusCode(204), ['filter' => 'kpapy:public']);";
            } else {
                $lines[] = '\\Kpapy\\Resource::routes($routes, ' . $path . ', ' . $handler . ", ['filter' => 'kpapy']);";
            }
        }

        return implode("\n", $lines);
    }

    /**
     * Language values of this table in one locale (merged into `Language/<locale>/<Module>.php`).
     *
     * @return array<string, mixed>
     */
    public function language(string $locale): array
    {
        $fields = [];

        foreach ($this->table->columns as $column) {
            $fields[$column->name] = $column->label->get($locale);
        }

        $common = [
            'created_by' => Texts::fixed('createdBy')->get($locale),
            'created_at' => Texts::fixed('createdAt')->get($locale),
            'updated_at' => Texts::fixed('updatedAt')->get($locale),
        ];

        $fields += $common;
        $texts = ['singular' => $this->table->singular->get($locale), 'plural' => $this->table->plural->get($locale), 'fields' => $fields];

        $options = [];

        foreach ($this->table->columns as $column) {
            if ($column->type === 'enum') {
                $options[$column->name] = array_combine($column->options, array_map(Texts::humanise(...), $column->options));
            }
        }

        if ($options !== []) {
            $texts['options'] = $options;
        }

        $operations = [];

        foreach ($this->table->crud->customOperations as $operation) {
            $operations[$operation['key']] = array_filter([
                'label'   => $operation['label']->get($locale),
                'confirm' => $operation['confirm']?->get($locale),
                'done'    => Texts::fixed('done')->get($locale),
            ], static fn ($v): bool => $v !== null && $v !== '');
        }

        if ($operations !== []) {
            $texts['ops'] = $operations;
        }

        if ($this->table->api->enabled) {
            $texts['api'] = [
                'summary'     => $this->table->plural->get($locale),
                'description' => Texts::fixed('generated')->get($locale),
            ];
        }

        $values = [
            'res'       => [$this->key => $this->table->plural->get($locale)],
            'menu'      => [$this->key => $this->table->plural->get($locale)],
            $this->key  => $texts,
        ];

        $extras = $this->extras();

        if ($extras !== []) {
            $values['perm'] = [$this->key => array_map(static fn (Texts $t): string => $t->get($locale), $extras)];
        }

        return $values;
    }

    /**
     * A line for the module's `CLAUDE.md` ("Resources and permissions").
     */
    public function claudeLine(): string
    {
        $line = "- `{$this->resource}` (crud: " . implode(', ', $this->table->operations()) . "): table `{$this->table->name}`"
            . ($this->table->translatedColumns() !== [] ? " (+ `{$this->table->translationTable()}`)" : '')
            . ", `<back office>/{$this->lower}/{$this->key}`";

        if ($this->table->api->enabled) {
            $line .= ', API `api/v1/' . $this->apiPath() . '`';
        }

        return $line . '. Generated by the code generator (definition in the Devtools wizard / `make:module --definition`).';
    }

    // ------------------------------------------------------------------ files

    /**
     * New files of this table: path inside the module => content.
     *
     * @param int $sequence first migration number (seconds added to the migration time stamp)
     *
     * @return array<string, string>
     */
    public function files(string $stamp, int &$sequence): array
    {
        $files = [];

        $files['Database/Migrations/' . self::stamp($stamp, $sequence++) . '_Create' . $this->table->studly() . '.php'] = $this->migration();

        if ($this->table->translatedColumns() !== []) {
            $files['Database/Migrations/' . self::stamp($stamp, $sequence++) . '_Create' . $this->table->studly() . 'Translations.php'] = $this->translationMigration();
        }

        if ($this->table->crud->enabled) {
            $files['Controllers/' . $this->class . '.php'] = $this->crudController();
            $files['Tests/' . $this->class . 'CrudTest.php'] = $this->crudTest();

            if ($this->table->crud->uploads !== []) {
                $files['Storage/' . $this->class . 'Uploads.php'] = $this->uploads();
            }
        }

        if ($this->table->api->enabled) {
            $files['Controllers/Api/' . $this->apiClass() . '.php'] = $this->table->api->public ? $this->publicApiController() : $this->apiController();
            $files['Tests/' . $this->apiClass() . 'ApiTest.php']    = $this->apiTest();
        }

        return $files;
    }

    /**
     * `2026-11-20-101500` + 2 seconds → `2026-11-20-101502` (migrations run in this order).
     */
    private static function stamp(string $stamp, int $offset): string
    {
        $time = \DateTimeImmutable::createFromFormat('Y-m-d-His', $stamp) ?: new \DateTimeImmutable();

        return $time->modify('+' . $offset . ' seconds')->format('Y-m-d-His');
    }

    /**
     * @param array<string, string> $values
     */
    private function template(string $name, array $values): string
    {
        $content = (string) file_get_contents(__DIR__ . '/Templates/' . $name . '.tpl');

        return strtr($content, ['{{Name}}' => $this->module, '{{name}}' => $this->lower, '{{key}}' => $this->key, '{{resource}}' => $this->resource, '{{table}}' => $this->table->name, ...$values]);
    }

    private function migration(): string
    {
        $fields = ['id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true]];
        $keys   = [];

        foreach ($this->table->plainColumns() as $column) {
            $fields[$column->name] = $this->forgeField($column);

            if ($column->unique) {
                $keys[] = '$this->forge->addUniqueKey(' . PhpExport::string($column->name) . ');';
            } elseif ($column->index || $column->foreign !== null) {
                $keys[] = '$this->forge->addKey(' . PhpExport::string($column->name) . ');';
            }
        }

        if ($this->table->needsOwnerColumn()) {
            $fields[CrudDefinition::OWNER_COLUMN] = ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true];
            $keys[]                               = "\$this->forge->addKey('" . CrudDefinition::OWNER_COLUMN . "');";
        }

        foreach ($this->table->plainColumns() as $column) {
            if ($column->foreign !== null) {
                $keys[] = '$this->forge->addForeignKey(' . PhpExport::string($column->name) . ', ' . PhpExport::string($column->foreign['table']) . ", 'id', '', " . PhpExport::string($column->foreign['onDelete']) . ');';
            }
        }

        if ($this->table->timestamps) {
            $fields['created_at'] = ['type' => 'DATETIME', 'null' => true];
            $fields['updated_at'] = ['type' => 'DATETIME', 'null' => true];
        }

        if ($this->table->softDeletes) {
            $fields['deleted_at'] = ['type' => 'DATETIME', 'null' => true];
        }

        return $this->template('Migration.php', [
            '{{class}}'  => 'Create' . $this->table->studly(),
            '{{doc}}'    => $this->docText(),
            '{{fields}}' => self::fieldLines($fields),
            '{{keys}}'   => $keys === [] ? '' : "\n        " . implode("\n        ", $keys),
        ]);
    }

    private function translationMigration(): string
    {
        $fk     = $this->table->translationKey();
        $fields = [
            'id'     => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            $fk      => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'locale' => ['type' => 'VARCHAR', 'constraint' => 10],
        ];

        foreach ($this->table->translatedColumns() as $column) {
            $fields[$column->name] = $this->forgeField($column, true);
        }

        return $this->template('Migration.php', [
            '{{class}}'  => 'Create' . $this->table->studly() . 'Translations',
            '{{doc}}'    => 'the translated columns of `' . $this->table->name . '` per language, Content convention',
            '{{table}}'  => $this->table->translationTable(),
            '{{fields}}' => self::fieldLines($fields),
            '{{keys}}'   => "\n        \$this->forge->addUniqueKey(['{$fk}', 'locale']);\n        \$this->forge->addForeignKey('{$fk}', '{$this->table->name}', 'id', '', 'CASCADE');",
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function forgeField(ColumnDefinition $column, bool $translated = false): array
    {
        $field = match ($column->type) {
            'string', 'enum' => ['type' => 'VARCHAR', 'constraint' => $column->length()],
            'text', 'json'   => ['type' => 'TEXT'],
            'integer'        => ['type' => 'INT', 'constraint' => 11],
            'bigint'         => ['type' => 'BIGINT', 'constraint' => 20],
            'decimal'        => ['type' => 'DECIMAL', 'constraint' => $column->precision . ',' . $column->scale],
            'boolean'        => ['type' => 'TINYINT', 'constraint' => 1],
            'date'           => ['type' => 'DATE'],
            'datetime'       => ['type' => 'DATETIME'],
            default          => ['type' => 'VARCHAR', 'constraint' => 190],
        };

        if ($column->foreign !== null) {
            $field['unsigned'] = true;
        }

        if ($column->nullable || $translated) {
            $field['null'] = true;
        }

        $default = $column->default ?? ($column->type === 'boolean' && ! $column->nullable ? '0' : null);

        if ($default !== null && ! $translated) {
            $field['default'] = in_array($column->type, ['integer', 'bigint', 'boolean'], true) ? (int) $default : $default;
        }

        return $field;
    }

    /**
     * @param array<string, array<string, mixed>> $fields
     */
    private static function fieldLines(array $fields): string
    {
        $width = max(array_map(static fn (string $k): int => strlen($k) + 2, array_keys($fields)));
        $lines = [];

        foreach ($fields as $name => $field) {
            $lines[] = '            ' . str_pad(PhpExport::string($name), $width) . ' => ' . PhpExport::array($field) . ',';
        }

        return implode("\n", $lines);
    }

    // ------------------------------------------------------------------ CRUD controller

    private function crudController(): string
    {
        $crud     = $this->table->crud;
        $uses     = ['CodeIgniter\\HTTP\\ResponseInterface', 'Modules\\Core\\Controllers\\BackendController'];
        $methods  = [];
        $notes    = [];
        $chain    = [];
        $hidden   = $crud->hidden;
        $owner    = $this->ruleColumn();
        $prefix   = $this->module . '.' . $this->key;
        $body     = ['$crud = $this->crud(' . PhpExport::string($this->resource) . ');'];
        $richText = $crud->richText;

        if ($owner !== null) {
            $body[] = '$me   = (int) auth()->id();';
        }

        if ($richText !== []) {
            $uses[]    = 'Modules\\Core\\Security\\HtmlSanitizer';
            $uses[]    = 'Modules\\Core\\Frontend\\RichTextEditor';
            array_unshift($body, '$this->sanitizePosted();', '');
            $notes[]   = 'Rich text (' . implode(', ', $richText) . '): the Core editor, sanitised with `HtmlSanitizer` before kpcrudy reads the form.';
            $methods[] = $this->sanitizeMethod();
        }

        $chain[] = '->setTable(' . PhpExport::string($this->table->name) . ')';
        $chain[] = '->setSubject(lang(' . PhpExport::string($prefix . '.singular') . '), lang(' . PhpExport::string($prefix . '.plural') . '))';

        $translated = $this->table->translatedColumns();

        if ($translated !== []) {
            $uses[]  = 'Modules\\Core\\Content\\ContentTranslations';
            $options = ['types' => [], 'rules' => []];

            foreach ($translated as $column) {
                $options['types'][$column->name] = $column->type === 'text' ? 'textarea' : 'text';
                $options['rules'][$column->name] = implode('|', $this->rules($column, translated: true));

                if (in_array($column->name, $richText, true)) {
                    $options['attrs'][$column->name] = new Code('RichTextEditor::attrs(RichTextEditor::images(' . PhpExport::string($this->lower) . '))');
                }
            }

            $chain[] = '->setTranslations(' . PhpExport::string($this->table->translationTable()) . ', ' . PhpExport::string($this->table->translationKey()) . ", 'locale', "
                . PhpExport::array(array_map(static fn (ColumnDefinition $c): string => $c->name, $translated)) . ', ContentTranslations::locales(), ContentTranslations::crudOptions('
                . PhpExport::array($options, '            ') . '))';
            $notes[] = 'Translated columns (' . implode(', ', array_map(static fn (ColumnDefinition $c): string => $c->name, $translated)) . ') in `' . $this->table->translationTable() . '` (`setTranslations()`, the site\'s languages).';
        }

        $hasOptions = false;

        foreach ($this->table->plainColumns() as $column) {
            $name = $column->name;

            if (in_array($name, $crud->uploads, true)) {
                $handler = $this->class . 'Uploads';
                $uses[]  = 'Modules\\' . $this->module . '\\Storage\\' . $handler;
                $chain[] = '->callbackField(' . PhpExport::string($name) . ', static fn ($value, ?array $row, array $field): string => ' . $handler . '::renderPicker(' . PhpExport::string($name) . ', $value, $field))';

                continue;
            }

            if ($column->foreign !== null) {
                $chain[] = '->setRelation(' . PhpExport::string($name) . ', ' . PhpExport::string($column->foreign['table']) . ', ' . PhpExport::string($this->displayColumn($column->foreign['table'])) . ')';

                continue;
            }

            $type = $this->fieldType($column);

            if ($type === null) {
                continue;
            }

            if ($column->type === 'enum') {
                $hasOptions = true;
                $chain[]    = '->fieldType(' . PhpExport::string($name) . ", 'select', ['options' => self::options(" . PhpExport::string($name) . ', ' . PhpExport::array($column->options) . ')])';
            } elseif (in_array($name, $richText, true)) {
                $chain[] = '->fieldType(' . PhpExport::string($name) . ", 'textarea', ['attrs' => RichTextEditor::attrs(RichTextEditor::images(" . PhpExport::string($this->lower) . '))])';
            } else {
                $chain[] = '->fieldType(' . PhpExport::string($name) . ', ' . PhpExport::string($type) . ')';
            }
        }

        if ($owner !== null) {
            $column = $this->table->column($owner);

            if ($column === null || $column->foreign === null) {
                $chain[] = '->setRelation(' . PhpExport::string($owner) . ", 'users', 'username')";
            }
        }

        $show = [...$this->table->fieldNames(), ...($this->table->timestamps ? ['created_at', 'updated_at'] : [])];

        $chain[] = '->columns(' . PhpExport::array($this->listColumns(), '            ') . ')';
        $chain[] = '->fields(' . PhpExport::array($this->formFields(), '            ') . ')';
        $chain[] = '->showFields(' . PhpExport::array(array_values(array_unique($show)), '            ') . ')';

        if ($crud->offers('export')) {
            $chain[] = '->exportColumns(' . PhpExport::array(array_values(array_diff($this->table->fieldNames(), $crud->uploads)), '            ') . ')';
        }

        if ($this->searchFields() !== []) {
            $chain[] = '->searchFields(' . PhpExport::array($this->searchFields(), '            ') . ')';
        }

        if ($this->filterFields() !== []) {
            $chain[] = '->filters(' . PhpExport::array($this->filterFields(), '            ') . ')';
        }

        $rules = [];

        foreach ($this->formFields() as $field) {
            $column = $this->table->column($field);

            if ($column !== null && ! $column->translated) {
                $rules[$field] = $this->ruleValue($this->rules($column));
            }
        }

        if ($rules !== []) {
            $chain[] = '->setRules(' . PhpExport::array($rules, '            ') . ')';
        }

        foreach ($this->table->plainColumns() as $column) {
            if ($column->default !== null && in_array($column->name, $this->formFields(), true)) {
                $chain[] = '->setDefault(' . PhpExport::string($column->name) . ', ' . PhpExport::string($column->default) . ')';
            }
        }

        $chain[]   = '->displayAs(self::labels())';
        $methods[] = $this->labelsMethod($show);

        $save = $owner !== null && $this->ownerOnInsert() !== null || $crud->uploads !== [] || $owner !== null && $crud->recordRule === 'column';

        if ($save) {
            $returns   = $crud->uploads !== [] ? 'array|string' : 'array';
            $chain[]   = '->beforeInsert(fn (array $data): ' . $returns . ' => $this->beforeSave($data, []' . ($owner !== null ? ', $me' : '') . '))';
            $chain[]   = '->beforeUpdate(fn (array $data, string $id, array $old): ' . $returns . ' => $this->beforeSave($data, $old' . ($owner !== null ? ', $me' : '') . '))';
            $methods[] = $this->beforeSaveMethod($owner);
        }

        $chain[] = "->defaultOrder('id', 'desc');";

        $body[] = '';
        $body[] = '$crud' . implode("\n            ", $chain);

        // Operations the resource does not offer.
        $unset = [];

        if ($crud->readOnly) {
            $unset[] = '->readOnly()';
        } else {
            foreach (['add' => 'unsetAdd', 'edit' => 'unsetEdit', 'delete' => 'unsetDelete'] as $operation => $method) {
                if (! $crud->offers($operation)) {
                    $unset[] = '->' . $method . '()';
                }
            }
        }

        if (! $crud->offers('show')) {
            $unset[] = '->unsetShow()';
        }

        if (! $crud->offers('export')) {
            $unset[] = '->unsetExport()';
        }

        if ($this->table->softDeletes && ! $crud->offers('trash')) {
            $unset[] = '->unsetTrash()';
        }

        if ($unset !== []) {
            $body[] = '';
            $body[] = '// Operations the resource does not declare (manifest `except`).';
            $body[] = '$crud' . implode('', $unset) . ';';
        }

        if ($crud->offers('import') && ! $crud->readOnly) {
            $body[] = '';
            $body[] = '$crud->enableImport();';
        }

        if (! $crud->maskIds) {
            $body[] = '';
            $body[] = '// Plain ids in the URLs (masked ids are the default; they are not access control).';
            $body[] = '$crud->maskIds(false);';
        }

        if ($owner !== null) {
            $body[] = '';
            $body[] = '// Record rule: without `' . $this->resource . '.all`, only the rows whose `' . $owner . '` is the user (list, show, edit, delete, bulk, export).';
            $body[] = 'if (! $this->can(' . PhpExport::string($this->resource . '.all') . ')) {';
            $body[] = '    $crud->where(' . PhpExport::string($owner) . ', $me);';

            if ($crud->recordRule === 'column') {
                $body[] = '    $crud->hideFields([' . PhpExport::string($owner) . ']);';
            }

            $body[] = '}';
            $notes[] = 'Record rule: users without `' . $this->resource . '.all` see and change only their rows (`' . $owner . '`).';
        }

        foreach ($hidden as $field => $permission) {
            $body[] = '';
            $body[] = '// `' . $field . '` exists only for users with `' . $this->resource . '.' . $permission . '` (list, details, forms, search, export, forged posts).';
            $body[] = 'if (! $this->can(' . PhpExport::string($this->resource . '.' . $permission) . ')) {';
            $body[] = '    $crud->hideFields([' . PhpExport::string($field) . ']);';
            $body[] = '}';
        }

        foreach ($crud->customOperations as $operation) {
            $key    = $operation['key'];
            $lang   = $prefix . '.ops.' . $key;
            $body[] = '';
            $body[] = '// Custom operation `' . $key . '` (permission `' . $this->resource . '.' . $key . '`)' . ($operation['reauth'] ? ', behind re-authentication' : '') . '.';
            $body[] = '$crud->addOperation(' . PhpExport::string($key) . ', ' . ($operation['reauth'] ? 'function' : 'static function') . ' (string $id, array $row): ' . ($operation['reauth'] ? 'ResponseInterface|string' : 'string') . ' {';

            if ($operation['reauth']) {
                $body[] = '    if (($response = $this->requireReauth()) !== null) {';
                $body[] = '        return $response;';
                $body[] = '    }';
                $body[] = '';
            }

            $body[] = '    // TODO: what the operation does with the row.';
            $body[] = '';
            $body[] = '    return lang(' . PhpExport::string($lang . '.done') . ');';
            $body[] = '}, [';
            $body[] = "    'label'      => lang(" . PhpExport::string($lang . '.label') . '),';
            $body[] = "    'icon'       => " . PhpExport::string($operation['icon']) . ',';

            if ($operation['confirm'] !== null) {
                $body[] = "    'confirm'    => lang(" . PhpExport::string($lang . '.confirm') . '),';
            }

            $body[] = "    'permission' => " . PhpExport::string($key) . ',';
            $body[] = ']);';
        }

        if ($hasOptions) {
            $methods[] = $this->optionsMethod();
        }

        sort($uses);

        return $this->template('CrudController.php', [
            '{{uses}}'    => implode("\n", array_map(static fn (string $u): string => 'use ' . $u . ';', array_unique($uses))),
            '{{doc}}'     => strtolower($this->docText()),
            '{{class}}'   => $this->class,
            '{{notes}}'   => $notes === [] ? '' : "\n *\n" . implode("\n", array_map(static fn (string $n): string => ' * - ' . $n, $notes)),
            '{{body}}'    => implode("\n", array_map(static fn (string $l): string => $l === '' ? '' : '        ' . $l, $body)),
            '{{methods}}' => implode('', array_map(static fn (string $m): string => "\n" . rtrim($m) . "\n", $methods)),
        ]);
    }

    private function fieldType(ColumnDefinition $column): ?string
    {
        $name = $column->name;

        return match ($column->type) {
            'string'            => str_contains($name, 'email') ? 'email' : (str_contains($name, 'url') ? 'url' : (str_contains($name, 'color') || str_contains($name, 'colour') ? 'color' : null)),
            'text', 'json'      => 'textarea',
            'integer', 'bigint' => 'number',
            'decimal'           => 'decimal',
            'boolean'           => 'boolean',
            'date'              => 'date',
            'datetime'          => 'datetime',
            'enum'              => 'select',
            default             => null,
        };
    }

    /**
     * The validation rules of a column.
     *
     * @return list<string>
     */
    public function rules(ColumnDefinition $column, bool $translated = false): array
    {
        $required = in_array($column->name, $this->required(), true);
        $rules    = [$required ? 'required' : 'permit_empty'];
        $name     = $column->name;

        if (in_array($name, $this->table->crud->uploads, true)) {
            return [...$rules, 'max_length[' . $column->length() . ']'];
        }

        $rules = [...$rules, ...match ($column->type) {
            'string'            => ['max_length[' . $column->length() . ']'],
            'enum'              => ['in_list[' . implode(',', $column->options) . ']'],
            'text'              => ['max_length[' . (in_array($name, $this->table->crud->richText, true) ? 65000 : 10000) . ']'],
            'json'              => ['valid_json'],
            'integer', 'bigint' => [$column->foreign !== null ? 'is_natural_no_zero' : 'integer'],
            'decimal'           => ['decimal'],
            'boolean'           => ['in_list[0,1]'],
            'date'              => ['valid_date[Y-m-d]'],
            'datetime'          => ['valid_date'],
            default             => [],
        }];

        if ($column->type === 'string' && str_contains($name, 'email')) {
            $rules[] = 'valid_email';
        } elseif ($column->type === 'string' && str_contains($name, 'url')) {
            $rules[] = 'valid_url_strict[https]';
        } elseif ($column->type === 'string' && (str_contains($name, 'color') || str_contains($name, 'colour'))) {
            $rules[] = 'regex_match[/^#[0-9a-fA-F]{6}$/]';
        }

        if ($column->unique && ! $translated) {
            $rules[] = 'is_unique[' . $this->table->name . '.' . $name . ',id,{id}]';
        }

        return $rules;
    }

    /**
     * A rule string, or a list when one rule contains `|`.
     *
     * @param list<string> $rules
     *
     * @return list<string>|string
     */
    private function ruleValue(array $rules): array|string
    {
        foreach ($rules as $rule) {
            if (str_contains($rule, '|')) {
                return $rules;
            }
        }

        return implode('|', $rules);
    }

    /** The column a relation shows: the first text column of the target table, or its id. */
    private function displayColumn(string $table): string
    {
        if ($table === 'users') {
            return 'username';
        }

        foreach ($this->definition->tables as $other) {
            if ($other->name === $table) {
                foreach ($other->plainColumns() as $column) {
                    if ($column->type === 'string') {
                        return $column->name;
                    }
                }

                return 'id';
            }
        }

        $fields = $this->context->fieldNames($table);

        foreach (['name', 'title', 'label', 'username', 'slug', 'code'] as $candidate) {
            if (in_array($candidate, $fields, true)) {
                return $candidate;
            }
        }

        return 'id';
    }

    /**
     * @param list<string> $show
     */
    private function labelsMethod(array $show): string
    {
        $fields = PhpExport::array(array_values(array_unique($show)), '        ');
        $lang   = PhpExport::string($this->module . '.' . $this->key . '.fields.');

        return <<<PHP
                /**
                 * @return array<string, string>
                 */
                private static function labels(): array
                {
                    \$labels = [];

                    foreach ({$fields} as \$field) {
                        \$labels[\$field] = lang({$lang} . \$field);
                    }

                    return \$labels;
                }

            PHP;
    }

    private function optionsMethod(): string
    {
        $lang = PhpExport::string($this->module . '.' . $this->key . '.options.');

        return <<<PHP
                /**
                 * Translated options of a select: value => label.
                 *
                 * @param list<string> \$values
                 *
                 * @return array<string, string>
                 */
                private static function options(string \$field, array \$values): array
                {
                    \$options = [];

                    foreach (\$values as \$value) {
                        \$options[\$value] = lang({$lang} . \$field . '.' . \$value);
                    }

                    return \$options;
                }

            PHP;
    }

    private function sanitizeMethod(): string
    {
        $plain      = [];
        $translated = [];

        foreach ($this->table->crud->richText as $field) {
            if ($this->table->column($field)?->translated === true) {
                $translated[] = $field;
            } else {
                $plain[] = $field;
            }
        }

        $loops = [];

        if ($plain !== []) {
            $list    = PhpExport::array($plain);
            $loops[] = <<<PHP
                        foreach ({$list} as \$field) {
                            if (array_key_exists(\$field, \$post)) {
                                \$post[\$field] = is_string(\$post[\$field]) ? HtmlSanitizer::clean(\$post[\$field]) : '';
                            }
                        }

                PHP;
        }

        if ($translated !== []) {
            $list    = PhpExport::array($translated);
            $loops[] = <<<PHP
                        foreach (is_array(\$post['kp_tr'] ?? null) ? \$post['kp_tr'] : [] as \$locale => \$fields) {
                            foreach ({$list} as \$field) {
                                if (is_array(\$fields) && array_key_exists(\$field, \$fields)) {
                                    \$post['kp_tr'][\$locale][\$field] = is_string(\$fields[\$field]) ? HtmlSanitizer::clean(\$fields[\$field]) : '';
                                }
                            }
                        }

                PHP;
        }

        $body = implode("\n", $loops);

        return <<<PHP
                /**
                 * Sanitises the posted rich text with HTMLPurifier before kpcrudy reads the form
                 * (plain fields and the translations in `kp_tr[<locale>][<field>]`).
                 */
                private function sanitizePosted(): void
                {
                    \$post = \$this->request->getPost();

                    if (\$post === []) {
                        return;
                    }

            {$body}
                    \$this->request->setGlobal('post', \$post);
                }

            PHP;
    }

    private function beforeSaveMethod(?string $owner): string
    {
        $lines = [];

        foreach ($this->table->crud->uploads as $field) {
            $f       = PhpExport::string($field);
            $lines[] = <<<PHP
                        if (array_key_exists({$f}, \$data)) {
                            \$current = is_string(\$old[{$f}] ?? null) ? \$old[{$f}] : null;
                            \$data[{$f}] = is_string(\$data[{$f}]) && trim(\$data[{$f}]) !== '' ? trim(\$data[{$f}]) : null;

                            if (! {$this->class}Uploads::accepts({$f}, \$data[{$f}], \$current)) {
                                return lang('Core.picker.errors.value');
                            }
                        }

                PHP;
        }

        if ($owner !== null) {
            $o       = PhpExport::string($owner);
            $lines[] = <<<PHP
                        // A new row belongs to the user who adds it.
                        if (\$old === [] && (\$data[{$o}] ?? null) === null) {
                            \$data[{$o}] = \$me;
                        }

                PHP;
        }

        $param   = $owner !== null ? ', int $me' : '';
        $body    = implode("\n", $lines);
        $returns = $this->table->crud->uploads !== [] ? 'array|string' : 'array';
        $doc     = $this->table->crud->uploads !== [] ? 'array<string, mixed>|string' : 'array<string, mixed>';

        return <<<PHP
                /**
                 * Prepares the data of an insert (`\$old` empty) or an update.
                 *
                 * @param array<string, mixed> \$data
                 * @param array<string, mixed> \$old
                 *
                 * @return {$doc}
                 */
                private function beforeSave(array \$data, array \$old{$param}): {$returns}
                {
            {$body}        return \$data;
                }

            PHP;
    }

    // ------------------------------------------------------------------ API controller

    private function apiController(): string
    {
        $crud  = $this->table->crud;
        $uses  = ['CodeIgniter\\Controller', 'CodeIgniter\\HTTP\\ResponseInterface', 'Kpapy\\Resource'];
        $notes = [];
        $chain = $this->apiChain($uses);
        $owner = $this->ruleColumn();
        $pre   = [];

        $after = [];

        if ($owner !== null || $crud->hidden !== []) {
            $uses[] = 'Kpapy\\Auth\\Context';
            $uses[] = 'Modules\\Core\\Permissions\\Authorization';
            $pre[]  = '$user = Context::user();';
        }

        if ($owner !== null) {
            $notes[] = 'Record rule as in the CRUD: without `' . $this->resource . '.all`, only the rows whose `' . $owner . '` is the token\'s user (also enforced on writes).';
            $after[] = '';
            $after[] = 'if ($user === null || ! Authorization::for($user)->can(' . PhpExport::string($this->resource . '.all') . ')) {';
            $after[] = '    $resource->where(' . PhpExport::string($owner) . ', $user === null ? 0 : (int) $user->id);';
            $after[] = '}';
        }

        foreach ($crud->hidden as $field => $permission) {
            $after[] = '';
            $after[] = 'if ($user === null || ! Authorization::for($user)->can(' . PhpExport::string($this->resource . '.' . $permission) . ')) {';
            $after[] = '    $resource->hideFields([' . PhpExport::string($field) . ']);';
            $after[] = '}';
        }

        if ($crud->readOnly) {
            $chain[] = '->readOnly()';
        } else {
            foreach (['add' => 'unsetAdd', 'edit' => 'unsetEdit', 'delete' => 'unsetDelete'] as $operation => $method) {
                if (! $crud->offers($operation)) {
                    $chain[] = '->' . $method . '()';
                }
            }
        }

        if (! $crud->offers('show')) {
            $chain[] = '->unsetShow()';
        }

        if (! $this->table->softDeletes || ! $crud->offers('trash')) {
            $chain[] = '->unsetTrash()';
        } elseif ($crud->readOnly) {
            $chain[] = '->unsetPurge()';
        }

        $chain[] = '->describe(lang(' . PhpExport::string($this->module . '.' . $this->key . '.api.summary') . '), lang(' . PhpExport::string($this->module . '.' . $this->key . '.api.description') . '));';

        $lines = [...$pre, ...($pre === [] ? [] : ['']), '$resource = (new Resource())' . "\n            " . implode("\n            ", $chain), ...$after, '', 'return $resource->render();'];

        sort($uses);

        return $this->template('ApiController.php', [
            '{{uses}}'  => implode("\n", array_map(static fn (string $u): string => 'use ' . $u . ';', array_unique($uses))),
            '{{doc}}'   => strtolower($this->docText()),
            '{{class}}' => $this->apiClass(),
            '{{path}}'  => $this->apiPath(),
            '{{intro}}' => 'the ' . strtolower($this->docText()) . " with the same permissions as their CRUD\n * (`setResource('{$this->resource}')`; a token's scopes are those permission names).",
            '{{notes}}' => $notes === [] ? '' : "\n *\n" . implode("\n", array_map(static fn (string $n): string => ' * ' . $n, $notes)),
            '{{body}}'  => implode("\n", array_map(static fn (string $l): string => $l === '' ? '' : '        ' . $l, $lines)),
        ]);
    }

    /**
     * The common part of both API controllers.
     *
     * @param list<string> $uses
     *
     * @return list<string>
     */
    private function apiChain(array &$uses): array
    {
        $chain = ['->setTable(' . PhpExport::string($this->table->name) . ')'];

        if (! $this->table->api->public) {
            $chain[] = '->setResource(' . PhpExport::string($this->resource) . ')';
        }

        $translated = $this->table->translatedColumns();

        if ($translated !== []) {
            $uses[]  = 'Modules\\Core\\Content\\ContentTranslations';
            $chain[] = '->setTranslations(' . PhpExport::string($this->table->translationTable()) . ', ' . PhpExport::string($this->table->translationKey()) . ", 'locale', "
                . PhpExport::array(array_map(static fn (ColumnDefinition $c): string => $c->name, $translated)) . ', ContentTranslations::codes(), ContentTranslations::apiOptions())';
        }

        foreach ($this->table->plainColumns() as $column) {
            $type = match ($column->type) {
                'boolean'  => 'bool',
                'date'     => 'date',
                'datetime' => 'datetime',
                'decimal'  => 'decimal',
                'json'     => 'json',
                default    => null,
            };

            if ($column->type === 'enum') {
                $chain[] = '->fieldType(' . PhpExport::string($column->name) . ", 'enum', ['options' => " . PhpExport::array(array_combine($column->options, $column->options)) . '])';
            } elseif ($type !== null) {
                $chain[] = '->fieldType(' . PhpExport::string($column->name) . ', ' . PhpExport::string($type) . ')';
            }
        }

        $fields = ['id', ...$this->table->fieldNames(), ...($this->table->timestamps ? ['created_at', 'updated_at'] : [])];
        $chain[] = '->fields(' . PhpExport::array($fields, '            ') . ')';
        $chain[] = '->listFields(' . PhpExport::array(['id', ...$this->listColumns()], '            ') . ')';

        if (! $this->table->api->public && ! $this->table->crud->readOnly) {
            $write = array_values(array_filter($this->formFields(), fn (string $f): bool => ! in_array($f, $this->table->crud->uploads, true)));
            $chain[] = '->writeFields(' . PhpExport::array($write, '            ') . ')';

            $required = array_values(array_filter($this->required(), fn (string $f): bool => in_array($f, $write, true) && $this->table->column($f)?->translated === false));

            if ($required !== []) {
                $chain[] = '->requiredFields(' . PhpExport::array($required) . ')';
            }
        }

        if ($this->filterFields() !== []) {
            $chain[] = '->filters(' . PhpExport::array($this->filterFields(), '            ') . ')';
        }

        if ($this->searchFields() !== []) {
            $chain[] = '->searchFields(' . PhpExport::array($this->searchFields(), '            ') . ')';
        }

        $chain[] = "->defaultOrder('id', 'desc')";

        return $chain;
    }

    private function publicApiController(): string
    {
        $uses  = ['CodeIgniter\\Controller', 'CodeIgniter\\HTTP\\ResponseInterface', 'Kpapy\\Resource'];
        $chain = $this->apiChain($uses);
        array_splice($chain, 1, 0, [
            '->setBasePath(' . PhpExport::string('api/v1/' . $this->apiPath()) . ')',
            '->setResource(' . PhpExport::string($this->lower . '-public-' . $this->key) . ')',
            '->setPermissionResolver(null)',
            "->setPermission('list', true)",
            "->setPermission('show', true)",
        ]);
        $chain[] = '->readOnly()';
        $chain[] = '->unsetTrash()';

        foreach (array_keys($this->table->crud->hidden) as $field) {
            $chain[] = '->hideFields([' . PhpExport::string($field) . '])';
        }

        if ($this->ruleColumn() !== null) {
            $chain[] = '->hideFields([' . PhpExport::string($this->ruleColumn()) . '])';
        }

        $chain[] = '->describe(lang(' . PhpExport::string($this->module . '.' . $this->key . '.api.summary') . '), lang(' . PhpExport::string($this->module . '.' . $this->key . '.api.description') . '))';
        $chain[] = '->render();';

        sort($uses);

        return $this->template('ApiController.php', [
            '{{uses}}'  => implode("\n", array_map(static fn (string $u): string => 'use ' . $u . ';', array_unique($uses))),
            '{{doc}}'   => strtolower($this->docText()),
            '{{class}}' => $this->apiClass(),
            '{{path}}'  => $this->apiPath(),
            '{{intro}}' => 'the public list of ' . strtolower($this->docText()) . '.',
            '{{notes}}' => "\n *\n * Public: no token (`kpapy:public`, GET routes only), no permission resolver, read only;\n * rate-limited per IP. Every row of the table is public: add a `where()` if some are not.",
            '{{body}}'  => '        return (new Resource())' . "\n            " . implode("\n            ", $chain),
        ]);
    }

    // ------------------------------------------------------------------ other files

    private function uploads(): string
    {
        return $this->template('Uploads.php', [
            '{{class}}'  => $this->class . 'Uploads',
            '{{fields}}' => implode(', ', $this->table->crud->uploads),
            '{{disk}}'   => 'public',
            '{{folder}}' => 'modules/' . $this->lower,
        ]);
    }

    private function crudTest(): string
    {
        $crud   = $this->table->crud;
        $list   = PhpExport::string($this->resource . '.list') . ', ' . PhpExport::string($this->resource . '.show');
        $forged = '';

        foreach ($crud->customOperations as $operation) {
            $forged .= "\n        \$this->assertCrudRefused(\$this->send('POST', \$list . '/" . $operation['key'] . "/' . \$this->code(\$id)), \$list, " . PhpExport::string($operation['key']) . ');';
        }

        $tests = [];

        if ($crud->offers('add') && ! $crud->readOnly) {
            $tests[] = <<<PHP
                    public function testAUserWithTheAddPermissionAddsARow(): void
                    {
                        \$this->signIn('{$this->resource}.list', '{$this->resource}.add');
                        \$rows = \$this->db->table('{$this->table->name}')->countAllResults();

                        \$this->send('POST', backend_path(self::LIST) . '/add', \$this->form())->assertRedirect();

                        \$this->assertSame(\$rows + 1, \$this->db->table('{$this->table->name}')->countAllResults());
                    }

                PHP;
        }

        if ($crud->readOnly) {
            $all     = implode(', ', array_map(fn (string $o): string => PhpExport::string($this->resource . '.' . $o), $this->table->operations()));
            $tests[] = <<<PHP
                    public function testTheCrudIsReadOnlyEvenWithEveryPermission(): void
                    {
                        \$this->signIn({$all});
                        \$rows = \$this->db->table('{$this->table->name}')->countAllResults();
                        \$list = backend_path(self::LIST);

                        \$this->assertCrudRefused(\$this->send('POST', \$list . '/add', \$this->form()), \$list, 'add');
                        \$this->assertSame(\$rows, \$this->db->table('{$this->table->name}')->countAllResults());
                    }

                PHP;
        }

        $owner = $this->ruleColumn();

        if ($owner !== null && $crud->offers('edit') && ! $crud->readOnly) {
            $field  = $this->firstEditableText();
            $change = $field === null ? "\$this->send('POST', backend_path(self::LIST) . '/delete/' . \$this->code(\$other));" : "\$this->send('POST', backend_path(self::LIST) . '/edit/' . \$this->code(\$other), ['{$field}' => 'Forged'] + \$this->form());";
            $check  = $field === null ? "\$this->assertNotNull(\$this->db->table('{$this->table->name}')->where('id', \$other)->get()->getRowArray());" : "\$this->assertNotSame('Forged', \$this->db->table('{$this->table->name}')->where('id', \$other)->get()->getRowArray()['{$field}'] ?? null);";
            $tests[] = <<<PHP
                    public function testWithoutAllAUserChangesOnlyTheirOwnRows(): void
                    {
                        \$this->signIn('{$this->resource}.list', '{$this->resource}.show', '{$this->resource}.edit');
                        \$other = \$this->row(['{$owner}' => (int) \$this->createUser('other@example.com')->id]);

                        // A row outside the user's scope is "not found".
                        {$change}

                        {$check}
                    }

                PHP;
        }

        foreach ($crud->hidden as $field => $permission) {
            $tests[] = <<<PHP
                    public function testThe{$this->studlyName($field)}FieldNeedsItsPermission(): void
                    {
                        \$this->signIn('{$this->resource}.list', '{$this->resource}.show', '{$this->resource}.add');
                        \$body = (string) \$this->send('GET', backend_path(self::LIST) . '/add')->response()->getBody();

                        \$this->assertStringNotContainsString('name="{$field}"', \$body);
                    }

                PHP;
        }

        foreach ($crud->customOperations as $operation) {
            if (! $operation['reauth']) {
                continue;
            }

            $key     = $operation['key'];
            $tests[] = <<<PHP
                    public function testThe{$this->studlyName($key)}OperationNeedsReauthentication(): void
                    {
                        \$this->signIn('{$this->resource}.list', '{$this->resource}.{$key}');
                        \$id = \$this->row();
                        Time::setTestNow(Time::now()->addMinutes(16));

                        try {
                            \$response = \$this->send('POST', backend_path(self::LIST) . '/{$key}/' . \$this->code(\$id));
                        } finally {
                            Time::setTestNow();
                        }

                        \$response->assertRedirect();
                        \$this->assertStringContainsString('reauth', \$response->response()->getHeaderLine('Location'));
                    }

                PHP;
        }

        $row     = [];
        $form    = [];
        $parents = [];

        foreach ($this->table->plainColumns() as $column) {
            $value = $this->validValue($column);

            if ($column->foreign !== null && ! $column->nullable) {
                $value     = $column->foreign['table'] === 'users' ? new Code('(int) auth()->id()') : new Code('$this->parentId(' . PhpExport::string($column->foreign['table']) . ')');
                $parents[] = $column->foreign['table'];
            }

            if (! $column->nullable && $column->default === null) {
                $row[$column->name] = $value;
            }

            if (in_array($column->name, $this->formFields(), true)) {
                $form[$column->name] = $value === null ? '' : (is_int($value) ? (string) $value : $value);

                if ($value instanceof Code) {
                    $form[$column->name] = new Code('(string) ' . $value->code);
                }
            }
        }

        if ($row === [] && ($first = $this->table->plainColumns()[0] ?? null) !== null) {
            // Every column has a default: the insert still needs one value.
            $row[$first->name] = $this->validValue($first);
        }

        if ($this->table->translatedColumns() !== []) {
            $texts = [];

            foreach ($this->table->translatedColumns() as $column) {
                $texts[$column->name] = (string) $this->validValue($column);
            }

            $form['kp_tr'] = Texts::each($texts);
        }

        $uses = [];

        if (array_filter($crud->customOperations, static fn (array $o): bool => $o['reauth']) !== []) {
            $uses[] = 'CodeIgniter\\I18n\\Time';
        }

        $content = $this->template('CrudTest.php', [
            '{{class}}'            => $this->class . 'CrudTest',
            '{{listPermissions}}'  => $list,
            '{{forgedOperations}}' => $forged,
            '{{tests}}'            => $tests === [] ? '' : "\n" . implode("\n", $tests),
            '{{row}}'              => PhpExport::array($row, '        '),
            '{{rowOwner}}'         => $owner === null ? '' : '[' . PhpExport::string($owner) . ' => auth()->id()] + ',
            '{{rowOwnerDoc}}'      => $owner === null ? '' : ' and owned by the signed-in user',
            '{{form}}'             => PhpExport::array($form, '        '),
            '{{helpers}}'          => $this->parentHelper(array_values(array_diff(array_unique($parents), ['users']))),
            '{{code}}'             => $crud->maskIds ? 'Crud::maskId($id, ' . PhpExport::string($this->table->name) . ')' : '(string) $id',
        ]);

        if (! $crud->offers('edit') || ! $crud->offers('delete') || $crud->readOnly) {
            // Operations the CRUD does not offer are refused all the same: the forged test stays.
        }

        foreach ($uses as $use) {
            $content = str_replace("use Kpcrudy\\Crud;\n", "use Kpcrudy\\Crud;\nuse {$use};\n", $content);
        }

        return $content;
    }

    /**
     * `parentId()` of the generated test: a row of a table a required foreign key points at
     * (tables of the definition get a valid row; others their first id).
     *
     * @param list<string> $tables
     */
    private function parentHelper(array $tables): string
    {
        if ($tables === []) {
            return '';
        }

        $rows = [];

        foreach ($this->definition->tables as $other) {
            if (in_array($other->name, $tables, true)) {
                $values = [];
                $code   = new self($this->definition, $other, $this->context);

                foreach ($other->plainColumns() as $column) {
                    if (! $column->nullable && $column->default === null && $column->foreign === null) {
                        $values[$column->name] = $code->validValue($column);
                    }
                }

                $rows[$other->name] = $values;
            }
        }

        $export = PhpExport::array($rows, '        ');

        return <<<PHP

                /** @var array<string, int> rows made by parentId() in this test */
                private array \$parents = [];

                /**
                 * A row of a table a required foreign key points at (made once per test).
                 */
                private function parentId(string \$table): int
                {
                    \$rows = {$export};

                    if (! isset(\$this->parents[\$table]) && isset(\$rows[\$table])) {
                        \$this->db->table(\$table)->insert(\$rows[\$table]);
                        \$this->parents[\$table] = (int) \$this->db->insertID();
                    }

                    if (! isset(\$this->parents[\$table])) {
                        \$row                    = \$this->db->table(\$table)->select('id')->get(1)->getRowArray();
                        \$this->parents[\$table] = is_array(\$row) ? (int) \$row['id'] : 1;
                    }

                    return \$this->parents[\$table];
                }

            PHP;
    }

    private function apiTest(): string
    {
        return $this->template($this->table->api->public ? 'PublicApiTest.php' : 'ApiTest.php', [
            '{{class}}' => $this->apiClass() . 'ApiTest',
            '{{path}}'  => $this->apiPath(),
        ]);
    }

    private function firstEditableText(): ?string
    {
        foreach ($this->formFields() as $field) {
            $column = $this->table->column($field);

            if ($column !== null && $column->type === 'string' && ! $column->translated && ! $column->unique && ! in_array($field, $this->table->crud->uploads, true) && ! str_contains($field, 'email') && ! str_contains($field, 'url') && ! str_contains($field, 'colo')) {
                return $field;
            }
        }

        return null;
    }

    private function studlyName(string $name): string
    {
        return str_replace(' ', '', ucwords(str_replace(['_', '-'], ' ', $name)));
    }

    /**
     * A value that passes the column's rules (tests and demo rows).
     */
    public function validValue(ColumnDefinition $column): mixed
    {
        if (in_array($column->name, $this->table->crud->uploads, true)) {
            return null;
        }

        if ($column->foreign !== null) {
            return $column->nullable ? null : 1;
        }

        if (in_array($column->name, $this->table->crud->richText, true)) {
            return '<p>Text</p>';
        }

        $value = $this->demo->value($column, 0);

        if ($value === null && ! $column->nullable) {
            $value = match ($column->type) {
                'integer', 'bigint', 'boolean' => 1,
                default                        => 'Text',
            };
        }

        return $value;
    }

    // ------------------------------------------------------------------ demo data

    /**
     * The demo seeder method of this table (indented for a class body) and its call.
     *
     * @return array{method: string, call: string, needsFirstId: bool}
     */
    public function demoMethod(): array
    {
        $rows         = [];
        $needsFirstId = false;
        $translated   = $this->table->translatedColumns();

        for ($i = 0; $i < $this->table->demoRows; $i++) {
            $row = [];

            foreach ($this->table->plainColumns() as $column) {
                if ($column->foreign !== null) {
                    if ($column->nullable) {
                        $row[$column->name] = null;
                    } else {
                        $row[$column->name] = new Code('$this->firstId(' . PhpExport::string($column->foreign['table']) . ')');
                        $needsFirstId       = true;
                    }

                    continue;
                }

                if (in_array($column->name, $this->table->crud->uploads, true)) {
                    continue;
                }

                $value = in_array($column->name, $this->table->crud->richText, true) ? '<p>' . $this->demo->value($column, $i) . '</p>' : $this->demo->value($column, $i);

                if ($value !== null || $column->nullable) {
                    $row[$column->name] = $value;
                }
            }

            if ($translated !== []) {
                $texts = [];

                foreach ($translated as $column) {
                    $value                = $this->demo->value($column, $i);
                    $texts[$column->name] = in_array($column->name, $this->table->crud->richText, true) ? '<p>' . $value . '</p>' : $value;
                }

                $rows[] = ['row' => $row, 'translations' => Texts::each($texts)];
            } else {
                $rows[] = $row;
            }
        }

        $method = 'seed' . $this->table->studly();
        $export = PhpExport::array($rows, '        ');
        $table  = PhpExport::string($this->table->name);

        if ($translated !== []) {
            $tr    = PhpExport::string($this->table->translationTable());
            $fk    = PhpExport::string($this->table->translationKey());
            $loop  = <<<PHP
                        foreach (\$rows as \$item) {
                            \$id = \$demo->insert({$table}, \$item['row']);

                            foreach (\$item['translations'] as \$locale => \$texts) {
                                \$demo->insert({$tr}, [{$fk} => \$id, 'locale' => \$locale] + \$texts);
                            }
                        }
                PHP;
        } else {
            $loop = <<<PHP
                        foreach (\$rows as \$row) {
                            \$demo->insert({$table}, \$row);
                        }
                PHP;
        }

        $code = <<<PHP
                /**
                 * {$this->table->demoRows} demo rows of `{$this->table->name}` (made with Faker when the code was generated).
                 */
                private function {$method}(DemoData \$demo): void
                {
                    \$rows = {$export};

            {$loop}
                }
            PHP;

        return ['method' => $code, 'call' => '$this->' . $method . '($demo);', 'needsFirstId' => $needsFirstId];
    }
}
