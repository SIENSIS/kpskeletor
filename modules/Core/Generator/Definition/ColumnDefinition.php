<?php

declare(strict_types=1);

namespace Modules\Core\Generator\Definition;

/**
 * A column of a generated table. Only portable types (MariaDB and SQLite): `string` (VARCHAR with a
 * length), `text`, `integer`, `bigint`, `decimal` (precision, scale), `boolean`, `date`,
 * `datetime`, `json` (stored as TEXT) and `enum` (a VARCHAR with an `in_list` rule over
 * `options`). A `foreign` key points at `<table>.id` with an ON DELETE rule. A `translated`
 * column lives in the `<table>_translations` table (Content convention), not in the table.
 */
final class ColumnDefinition
{
    public const TYPES = ['string', 'text', 'integer', 'bigint', 'decimal', 'boolean', 'date', 'datetime', 'json', 'enum'];

    public const ON_DELETE = ['CASCADE', 'SET NULL', 'RESTRICT', 'NO ACTION'];

    /** Column names the generator adds itself (timestamps, trash, owner). */
    public const RESERVED = ['id', 'created_at', 'updated_at', 'deleted_at'];

    /**
     * @param list<string>                                            $options enum values
     * @param array{table: string, column: string, onDelete: string}|null $foreign
     */
    public function __construct(
        public readonly string $name,
        public readonly string $type,
        public readonly Texts $label,
        public readonly ?int $length = null,
        public readonly int $precision = 10,
        public readonly int $scale = 2,
        public readonly bool $nullable = false,
        public readonly ?string $default = null,
        public readonly bool $unique = false,
        public readonly bool $index = false,
        public readonly ?array $foreign = null,
        public readonly bool $translated = false,
        public readonly array $options = [],
        public readonly ?string $faker = null,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $name = is_string($data['name'] ?? null) ? trim($data['name']) : '';
        $type = is_string($data['type'] ?? null) ? strtolower(trim($data['type'])) : 'string';

        $foreign = null;

        if (is_array($data['foreign'] ?? null) && is_string($data['foreign']['table'] ?? null) && trim($data['foreign']['table']) !== '') {
            $foreign = [
                'table'    => trim($data['foreign']['table']),
                'column'   => is_string($data['foreign']['column'] ?? null) && trim($data['foreign']['column']) !== '' ? trim($data['foreign']['column']) : 'id',
                'onDelete' => strtoupper(is_string($data['foreign']['onDelete'] ?? null) ? trim($data['foreign']['onDelete']) : 'RESTRICT'),
            ];
        }

        $options = [];

        if (is_array($data['options'] ?? null)) {
            $options = array_values(array_filter(array_map(static fn ($v): string => is_scalar($v) ? trim((string) $v) : '', $data['options']), static fn (string $v): bool => $v !== ''));
        } elseif (is_string($data['options'] ?? null)) {
            $options = array_values(array_filter(array_map(trim(...), explode(',', $data['options'])), static fn (string $v): bool => $v !== ''));
        }

        $default = $data['default'] ?? null;

        return new self(
            name: $name,
            type: $type,
            label: Texts::from($data['label'] ?? null, Texts::humanise($name)),
            length: self::int($data['length'] ?? null),
            precision: self::int($data['precision'] ?? null) ?? 10,
            scale: self::int($data['scale'] ?? null) ?? 2,
            nullable: self::bool($data['nullable'] ?? false),
            default: is_scalar($default) && (string) $default !== '' ? (is_bool($default) ? ($default ? '1' : '0') : (string) $default) : null,
            unique: self::bool($data['unique'] ?? false),
            index: self::bool($data['index'] ?? false),
            foreign: $foreign,
            translated: self::bool($data['translated'] ?? false),
            options: $options,
            faker: is_string($data['faker'] ?? null) && trim($data['faker']) !== '' ? trim($data['faker']) : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter([
            'name'       => $this->name,
            'type'       => $this->type,
            'label'      => $this->label->toArray(),
            'length'     => $this->type === 'string' || $this->type === 'enum' ? $this->length() : null,
            'precision'  => $this->type === 'decimal' ? $this->precision : null,
            'scale'      => $this->type === 'decimal' ? $this->scale : null,
            'nullable'   => $this->nullable,
            'default'    => $this->default,
            'unique'     => $this->unique,
            'index'      => $this->index,
            'foreign'    => $this->foreign,
            'translated' => $this->translated,
            'options'    => $this->options,
            'faker'      => $this->faker,
        ], static fn ($v): bool => $v !== null && $v !== [] && $v !== false);
    }

    /** The VARCHAR length of `string` and `enum` columns. */
    public function length(): int
    {
        return $this->length ?? ($this->type === 'enum' ? 20 : 190);
    }

    public static function int(mixed $value): ?int
    {
        return is_int($value) || (is_string($value) && preg_match('/^\d+$/', trim($value)) === 1) ? (int) $value : null;
    }

    public static function bool(mixed $value): bool
    {
        return $value === true || $value === 1 || in_array($value, ['1', 'true', 'on', 'yes'], true);
    }
}
