<?php

declare(strict_types=1);

namespace Modules\Core\Generator;

use Modules\Core\Generator\Definition\ColumnDefinition;

/**
 * The demo values of a generated table. The values are made **when the code is generated**, with
 * Faker (a development dependency) seeded by the table name, and written into the demo seeder as
 * literals: the seeder needs no Faker, works on a `--no-dev` installation and gives the same rows
 * every time. Without Faker, simple numbered values are used.
 */
final class DemoValues
{
    /** Generators offered per column (`faker` of the definition). */
    public const GENERATORS = [
        'words', 'sentence', 'paragraph', 'name', 'firstName', 'lastName', 'company', 'email', 'phone',
        'city', 'address', 'url', 'slug', 'color', 'number', 'price', 'boolean', 'date', 'datetime',
        'futureDate', 'option', 'json', 'null',
    ];

    private ?object $faker = null;

    public function __construct(string $seed)
    {
        if (class_exists(\Faker\Factory::class)) {
            $this->faker = \Faker\Factory::create('es_ES');
            $this->faker->seed(crc32($seed));
        }
    }

    /**
     * The generator used for a column: the one chosen, or a sensible default from its name and type.
     */
    public static function defaultFor(ColumnDefinition $column): string
    {
        if ($column->faker !== null && in_array($column->faker, self::GENERATORS, true)) {
            return $column->faker;
        }

        $name = $column->name;

        return match (true) {
            $column->foreign !== null                                    => 'null',
            $column->type === 'enum'                                     => 'option',
            $column->type === 'boolean'                                  => 'boolean',
            $column->type === 'date'                                     => str_contains($name, 'due') || str_contains($name, 'end') ? 'futureDate' : 'date',
            $column->type === 'datetime'                                 => 'datetime',
            $column->type === 'decimal'                                  => 'price',
            $column->type === 'integer', $column->type === 'bigint'      => 'number',
            $column->type === 'json'                                     => 'json',
            $column->type === 'text'                                     => 'paragraph',
            str_contains($name, 'email')                                 => 'email',
            str_contains($name, 'phone')                                 => 'phone',
            str_contains($name, 'url') || str_contains($name, 'web')     => 'url',
            str_contains($name, 'slug')                                  => 'slug',
            str_contains($name, 'color') || str_contains($name, 'colour') => 'color',
            str_contains($name, 'city')                                  => 'city',
            str_contains($name, 'address')                               => 'address',
            str_contains($name, 'company')                               => 'company',
            in_array($name, ['first_name', 'firstname'], true)           => 'firstName',
            in_array($name, ['last_name', 'lastname', 'surname'], true)  => 'lastName',
            str_contains($name, 'name') || str_contains($name, 'author') => 'name',
            str_contains($name, 'title') || str_contains($name, 'subject') => 'sentence',
            default                                                      => 'words',
        };
    }

    /**
     * A demo value for a column in row `$row` (0-based).
     */
    public function value(ColumnDefinition $column, int $row): mixed
    {
        $generator = self::defaultFor($column);

        if ($generator === 'null') {
            return $column->nullable ? null : ($column->foreign !== null ? null : $this->fallback($column, $row));
        }

        $value = $this->faker !== null ? $this->fake($generator, $column, $row) : $this->fallback($column, $row);

        return $this->fit($column, $value, $row, $generator);
    }

    private function fake(string $generator, ColumnDefinition $column, int $row): mixed
    {
        /** @var \Faker\Generator $f */
        $f = $this->faker;

        return match ($generator) {
            'words'      => ucfirst(implode(' ', (array) $f->words(3))),
            'sentence'   => rtrim($f->sentence(5), '.'),
            'paragraph'  => $f->paragraph(3),
            'name'       => $f->name(),
            'firstName'  => $f->firstName(),
            'lastName'   => $f->lastName(),
            'company'    => $f->company(),
            'email'      => 'demo' . ($row + 1) . '@example.com',
            'phone'      => $f->numerify('6## ### ###'),
            'city'       => $f->city(),
            'address'    => $f->streetAddress(),
            'url'        => 'https://example.com/' . $f->slug(2),
            'slug'       => $f->slug(3) . '-' . ($row + 1),
            'color'      => $f->hexColor(),
            'number'     => $f->numberBetween(1, 100),
            'price'      => number_format($f->randomFloat(2, 1, 500), $column->scale, '.', ''),
            'boolean'    => $f->boolean() ? 1 : 0,
            'date'       => $f->dateTimeBetween('-1 year', 'now')->format('Y-m-d'),
            'futureDate' => $f->dateTimeBetween('now', '+6 months')->format('Y-m-d'),
            'datetime'   => $f->dateTimeBetween('-1 year', 'now')->format('Y-m-d H:i:s'),
            'option'     => $column->options === [] ? null : $column->options[$row % count($column->options)],
            'json'       => json_encode(['key' => $f->word()]),
            default      => $this->fallback($column, $row),
        };
    }

    private function fallback(ColumnDefinition $column, int $row): mixed
    {
        $n = $row + 1;

        return match ($column->type) {
            'integer', 'bigint' => $n,
            'decimal'           => number_format($n * 10, $column->scale, '.', ''),
            'boolean'           => $n % 2,
            'date'              => sprintf('2026-01-%02d', min($n, 28)),
            'datetime'          => sprintf('2026-01-%02d 10:00:00', min($n, 28)),
            'enum'              => $column->options === [] ? null : $column->options[$row % count($column->options)],
            'json'              => '{}',
            default             => $column->label->get('ca') . ' ' . $n,
        };
    }

    /**
     * Cuts strings to the column's length and numbers the values of unique columns.
     */
    private function fit(ColumnDefinition $column, mixed $value, int $row, string $generator): mixed
    {
        if (! is_string($value) || ! in_array($column->type, ['string', 'text'], true)) {
            return $value;
        }

        $suffix = $column->unique && ! in_array($generator, ['email', 'slug'], true) ? ' ' . ($row + 1) : '';
        $max    = $column->type === 'string' ? $column->length() : 2000;

        return mb_substr($value, 0, max(1, $max - mb_strlen($suffix))) . $suffix;
    }
}
