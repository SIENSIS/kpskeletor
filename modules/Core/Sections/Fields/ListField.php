<?php

declare(strict_types=1);

namespace Modules\Core\Sections\Fields;

use InvalidArgumentException;
use Modules\Core\Sections\FieldContext;
use Modules\Core\Sections\SectionValidationException;

/**
 * Repeated items with their own fields (cards, links…), between a minimum and a maximum, added,
 * removed and reordered in the form (`core/sections/form`). Lists do not nest.
 *
 * Posted: a list of items, each one `[field => posted value]`, in the order of the form. Stored:
 * the items' non-translated parts and, per language, the items' translated parts (same order).
 * Resolved: a list of items, each one `[field => resolved value]`.
 */
class ListField extends Field
{
    /** @var array<string, Field> */
    protected array $fields = [];

    protected int $min = 0;

    protected int $max = 12;

    public function type(): string
    {
        return 'list';
    }

    /**
     * @param list<Field> $fields
     */
    public function fields(array $fields): static
    {
        $this->fields = [];

        foreach ($fields as $field) {
            if ($field instanceof self) {
                throw new InvalidArgumentException('Lists of a section cannot nest.');
            }

            $this->fields[$field->name] = $field;
        }

        return $this;
    }

    /**
     * @return array<string, Field>
     */
    public function getFields(): array
    {
        return $this->fields;
    }

    public function items(int $min, int $max): static
    {
        $this->min = max(0, min($min, $max));
        $this->max = max(1, $max, $min);

        return $this;
    }

    public function getMin(): int
    {
        return $this->min;
    }

    public function getMax(): int
    {
        return $this->max;
    }

    public function translated(bool $translated = true): static
    {
        return $this; // its fields are translated one by one
    }

    public function emptyValue(): mixed
    {
        return [];
    }

    public function clean(mixed $posted, FieldContext $context, string $path): array
    {
        $posted = $posted ?? [];

        if (! is_array($posted)) {
            throw SectionValidationException::at($path, lang('Sections.errors.invalid', [$this->getLabel()]));
        }

        $items = array_values($posted);

        if (count($items) > $this->max) {
            throw SectionValidationException::at($path, lang('Sections.errors.tooMany', [$this->getLabel(), $this->max]));
        }

        if (count($items) < max($this->min, $this->required ? 1 : 0)) {
            throw SectionValidationException::at($path, lang('Sections.errors.tooFew', [$this->getLabel(), max($this->min, 1)]));
        }

        $values = [];
        $texts  = array_fill_keys($context->locales, []);

        foreach ($items as $index => $item) {
            if (! is_array($item)) {
                throw SectionValidationException::at($path . '.' . $index, lang('Sections.errors.invalid', [$this->getLabel()]));
            }

            $unknown = array_diff(array_map('strval', array_keys($item)), array_keys($this->fields));

            if ($unknown !== []) {
                throw SectionValidationException::at($path . '.' . $index . '.' . reset($unknown), lang('Sections.errors.unknownField', [reset($unknown)]));
            }

            $value = [];
            $text  = array_fill_keys($context->locales, []);

            foreach ($this->fields as $name => $field) {
                $clean = $field->clean($item[$name] ?? null, $context, $path . '.' . $index . '.' . $name);

                if (array_key_exists('value', $clean)) {
                    $value[$name] = $clean['value'];
                }

                foreach ($clean['texts'] ?? [] as $locale => $part) {
                    $text[$locale][$name] = $part;
                }
            }

            $values[] = $value;

            foreach ($text as $locale => $parts) {
                $texts[$locale][] = $parts;
            }
        }

        return ['value' => $values, 'texts' => $texts];
    }

    public function resolve(mixed $value, array $texts, string $locale, string $default): mixed
    {
        $items    = is_array($value) ? array_values($value) : [];
        $resolved = [];

        foreach ($items as $index => $item) {
            $item = is_array($item) ? $item : [];
            $row  = [];

            foreach ($this->fields as $name => $field) {
                $row[$name] = $field->resolve($item[$name] ?? null, self::column($texts, $index, $name), $locale, $default);
            }

            $resolved[] = $row;
        }

        return $resolved;
    }

    public function formValue(mixed $value, array $texts): mixed
    {
        $items = is_array($value) ? array_values($value) : [];
        $form  = [];

        foreach ($items as $index => $item) {
            $item = is_array($item) ? $item : [];
            $row  = [];

            foreach ($this->fields as $name => $field) {
                $row[$name] = $field->formValue($item[$name] ?? null, self::column($texts, $index, $name));
            }

            $form[] = $row;
        }

        return $form;
    }

    public function summary(mixed $resolved): string
    {
        return is_array($resolved) && $resolved !== [] ? lang('Sections.items', [count($resolved)]) : '';
    }

    public function fileKeys(mixed $value): array
    {
        $keys = [];

        foreach (is_array($value) ? $value : [] as $item) {
            foreach ($this->fields as $name => $field) {
                array_push($keys, ...$field->fileKeys(is_array($item) ? ($item[$name] ?? null) : null));
            }
        }

        return $keys;
    }

    /**
     * One item's part of a field in every language.
     *
     * @param array<string, mixed> $texts locale => list of items
     *
     * @return array<string, mixed> locale => part
     */
    private static function column(array $texts, int $index, string $name): array
    {
        $column = [];

        foreach ($texts as $locale => $items) {
            if (is_array($items) && is_array($items[$index] ?? null) && array_key_exists($name, $items[$index])) {
                $column[(string) $locale] = $items[$index][$name];
            }
        }

        return $column;
    }
}
