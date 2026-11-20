<?php

declare(strict_types=1);

namespace Modules\Core\Generator\Definition;

/**
 * The kpcrudy CRUD of a generated table (docs/cruds.md; every option has a recipe in Devtools ›
 * CRUD recipes): the list columns, form fields, required fields, search and filters, the standard
 * operations offered (or read only), custom operations (`addOperation()`, optionally behind
 * re-authentication), the record rule (everyone, own rows, rows whose column holds the user's
 * id), fields hidden without an extra permission (`hideFields()`), file-picker fields (Storage
 * service), rich-text fields (Core editor + `HtmlSanitizer`) and masked ids.
 */
final class CrudDefinition
{
    /** Operations a generated CRUD may offer (restore and purge come with the trash). */
    public const OPERATIONS = ['list', 'show', 'add', 'edit', 'delete', 'trash', 'export', 'import'];

    public const WRITE_OPERATIONS = ['add', 'edit', 'delete', 'import'];

    public const RECORD_RULES = ['everyone', 'own', 'column'];

    /** Owner column of the `own` record rule. */
    public const OWNER_COLUMN = 'created_by';

    /**
     * @param list<string>                                                                     $operations
     * @param list<string>                                                                     $columns
     * @param list<string>                                                                     $fields
     * @param list<string>                                                                     $required
     * @param list<string>                                                                     $search
     * @param list<string>                                                                     $filters
     * @param list<array{key: string, label: Texts, icon: string, confirm: ?Texts, reauth: bool}> $customOperations
     * @param array<string, string>                                                            $hidden     field => extra permission
     * @param list<string>                                                                     $uploads
     * @param list<string>                                                                     $richText
     */
    public function __construct(
        public readonly bool $enabled = true,
        public readonly bool $readOnly = false,
        public readonly array $operations = self::OPERATIONS,
        public readonly array $columns = [],
        public readonly array $fields = [],
        public readonly array $required = [],
        public readonly array $search = [],
        public readonly array $filters = [],
        public readonly array $customOperations = [],
        public readonly string $recordRule = 'everyone',
        public readonly ?string $ruleColumn = null,
        public readonly array $hidden = [],
        public readonly array $uploads = [],
        public readonly array $richText = [],
        public readonly bool $maskIds = true,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $operations = isset($data['operations']) ? self::names($data['operations']) : self::OPERATIONS;
        $readOnly   = ColumnDefinition::bool($data['readOnly'] ?? false);

        if ($readOnly) {
            $operations = array_values(array_diff($operations, self::WRITE_OPERATIONS));
        }

        if (! in_array('list', $operations, true)) {
            array_unshift($operations, 'list');
        }

        $custom = [];

        foreach (is_array($data['customOperations'] ?? null) ? $data['customOperations'] : [] as $operation) {
            if (! is_array($operation) || ! is_string($operation['key'] ?? null) || trim($operation['key']) === '') {
                continue;
            }

            $key      = trim($operation['key']);
            $custom[] = [
                'key'     => $key,
                'label'   => Texts::from($operation['label'] ?? null, Texts::humanise($key)),
                'icon'    => is_string($operation['icon'] ?? null) && trim($operation['icon']) !== '' ? trim($operation['icon']) : 'bi bi-lightning',
                'confirm' => self::filled($operation['confirm'] ?? null) ? Texts::from($operation['confirm'], '') : null,
                'reauth'  => ColumnDefinition::bool($operation['reauth'] ?? false),
            ];
        }

        $hidden = [];

        foreach (is_array($data['hidden'] ?? null) ? $data['hidden'] : [] as $field => $permission) {
            if (is_array($permission)) {
                $field      = $permission['field'] ?? '';
                $permission = $permission['permission'] ?? '';
            }

            if (is_string($field) && trim($field) !== '' && is_string($permission) && trim($permission) !== '') {
                $hidden[trim($field)] = trim($permission);
            }
        }

        $rule = is_string($data['recordRule'] ?? null) ? $data['recordRule'] : 'everyone';

        return new self(
            enabled: ! array_key_exists('enabled', $data) || ColumnDefinition::bool($data['enabled']),
            readOnly: $readOnly,
            operations: array_values(array_intersect(self::OPERATIONS, $operations)),
            columns: self::names($data['columns'] ?? []),
            fields: self::names($data['fields'] ?? []),
            required: self::names($data['required'] ?? []),
            search: self::names($data['search'] ?? []),
            filters: self::names($data['filters'] ?? []),
            customOperations: $custom,
            recordRule: $rule,
            ruleColumn: is_string($data['ruleColumn'] ?? null) && trim($data['ruleColumn']) !== '' ? trim($data['ruleColumn']) : null,
            hidden: $hidden,
            uploads: self::names($data['uploads'] ?? []),
            richText: self::names($data['richText'] ?? []),
            maskIds: ! array_key_exists('maskIds', $data) || ColumnDefinition::bool($data['maskIds']),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'enabled'          => $this->enabled,
            'readOnly'         => $this->readOnly,
            'operations'       => $this->operations,
            'columns'          => $this->columns,
            'fields'           => $this->fields,
            'required'         => $this->required,
            'search'           => $this->search,
            'filters'          => $this->filters,
            'customOperations' => array_map(static fn (array $o): array => [
                'key'     => $o['key'],
                'label'   => $o['label']->toArray(),
                'icon'    => $o['icon'],
                'confirm' => $o['confirm']?->toArray(),
                'reauth'  => $o['reauth'],
            ], $this->customOperations),
            'recordRule' => $this->recordRule,
            'ruleColumn' => $this->ruleColumn,
            'hidden'     => $this->hidden,
            'uploads'    => $this->uploads,
            'richText'   => $this->richText,
            'maskIds'    => $this->maskIds,
        ];
    }

    public function offers(string $operation): bool
    {
        return in_array($operation, $this->operations, true);
    }

    /** The column holding the user's id for the `own` / `column` record rules, or null. */
    public function ownerColumn(): ?string
    {
        return match ($this->recordRule) {
            'own'    => self::OWNER_COLUMN,
            'column' => $this->ruleColumn,
            default  => null,
        };
    }

    /**
     * @return list<string>
     */
    public static function names(mixed $value): array
    {
        if (is_string($value)) {
            $value = explode(',', $value);
        }

        if (! is_array($value)) {
            return [];
        }

        $names = [];

        foreach ($value as $name) {
            if (is_string($name) && trim($name) !== '' && ! in_array(trim($name), $names, true)) {
                $names[] = trim($name);
            }
        }

        return $names;
    }

    private static function filled(mixed $value): bool
    {
        if (is_string($value)) {
            return trim($value) !== '';
        }

        return is_array($value) && array_filter($value, static fn ($v): bool => is_string($v) && trim($v) !== '') !== [];
    }
}
