<?php

declare(strict_types=1);

namespace Modules\Core\Generator\Definition;

/**
 * What the generator builds (`make:module --definition=<file.json>`, Devtools › Code generator):
 * a new module (name, description, author, dependencies) or an existing one (`existing`), and its
 * tables. Serialisable as JSON (`fromJson()`, `toJson()`); `docs/devtools.md` describes the format.
 */
final class ModuleDefinition implements \JsonSerializable
{
    public const FORMAT = 1;

    /**
     * @param array<string, string> $requires module => constraint (Core is always required)
     * @param list<TableDefinition> $tables
     */
    public function __construct(
        public readonly string $name,
        public readonly bool $existing = false,
        public readonly ?Texts $description = null,
        public readonly string $author = '',
        public readonly array $requires = [],
        public readonly array $tables = [],
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $module   = is_array($data['module'] ?? null) ? $data['module'] : $data;
        $name     = is_string($module['name'] ?? null) ? trim($module['name']) : '';
        $requires = [];

        foreach (is_array($module['requires'] ?? null) ? $module['requires'] : [] as $dependency => $constraint) {
            if (is_int($dependency) && is_string($constraint)) {
                [$dependency, $constraint] = [$constraint, '*'];
            }

            if (is_string($dependency) && trim($dependency) !== '' && trim($dependency) !== 'Core' && is_string($constraint)) {
                $requires[trim($dependency)] = trim($constraint) === '' ? '*' : trim($constraint);
            }
        }

        $tables = [];

        foreach (is_array($data['tables'] ?? null) ? $data['tables'] : [] as $table) {
            if (is_array($table)) {
                $tables[] = TableDefinition::fromArray($table);
            }
        }

        $description = $module['description'] ?? null;

        return new self(
            name: $name,
            existing: ColumnDefinition::bool($module['existing'] ?? false),
            description: (is_string($description) && trim($description) !== '') || is_array($description) ? Texts::from($description, '') : null,
            author: is_string($module['author'] ?? null) ? trim($module['author']) : '',
            requires: $requires,
            tables: $tables,
        );
    }

    /**
     * @throws \InvalidArgumentException when the JSON is not an object
     */
    public static function fromJson(string $json): self
    {
        try {
            $data = json_decode($json, true, 64, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new \InvalidArgumentException(lang('Core.generator.invalidJson', [$e->getMessage()]), 0, $e);
        }

        if (! is_array($data)) {
            throw new \InvalidArgumentException(lang('Core.generator.invalidJson', ['']));
        }

        return self::fromArray($data);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'format' => self::FORMAT,
            'module' => ['name' => $this->name, 'existing' => $this->existing] + array_filter([
                'description' => $this->description?->toArray(),
                'author'      => $this->author,
                'requires'    => $this->requires,
            ], static fn ($v): bool => $v !== null && $v !== '' && $v !== []),
            'tables' => array_map(static fn (TableDefinition $t): array => $t->toArray(), $this->tables),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    public function toJson(): string
    {
        return json_encode($this, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n";
    }

    public function lower(): string
    {
        return strtolower($this->name);
    }

    /**
     * A copy with other tables (the wizard adds and removes them).
     *
     * @param list<TableDefinition> $tables
     */
    public function withTables(array $tables): self
    {
        return new self($this->name, $this->existing, $this->description, $this->author, $this->requires, $tables);
    }
}
