<?php

declare(strict_types=1);

namespace Modules\Core\Sections;

/**
 * Validates a posted section against its type's schema (forged posts included: unknown fields,
 * wrong shapes, too many items, links and images the editor may not use) and gives the values
 * the form shows.
 */
final class SectionForm
{
    public function __construct(private readonly SectionType $type, private readonly FieldContext $context)
    {
    }

    /**
     * @param mixed $fields  the posted fields (`f[...]`): field => posted value
     * @param mixed $options the posted common options (`o[...]`)
     *
     * @throws SectionValidationException
     */
    public function validate(mixed $fields, mixed $options, bool $hidden = false, ?int $id = null): SectionData
    {
        $fields = $fields ?? [];

        if (! is_array($fields)) {
            throw SectionValidationException::at('fields', lang('Sections.errors.options'));
        }

        $schema  = $this->type->fields();
        $unknown = array_diff(array_map('strval', array_keys($fields)), array_keys($schema));

        if ($unknown !== []) {
            throw SectionValidationException::at((string) reset($unknown), lang('Sections.errors.unknownField', [reset($unknown)]));
        }

        $values = [];
        $texts  = array_fill_keys($this->context->locales, []);

        foreach ($schema as $name => $field) {
            $clean = $field->clean($fields[$name] ?? null, $this->context, $name);

            if (array_key_exists('value', $clean)) {
                $values[$name] = $clean['value'];
            }

            foreach ($clean['texts'] ?? [] as $locale => $part) {
                $texts[$locale][$name] = $part;
            }
        }

        $section = new SectionData($this->type->key(), $values, $texts, SectionOptions::clean($options, $this->type, $this->context), $hidden, $id);
        $this->type->check($section, $this->context);

        return $section;
    }

    /**
     * The values the form shows for a stored section (or a new one: empty).
     *
     * @return array<string, mixed> field => posted shape
     */
    public function formValues(?SectionData $section): array
    {
        $values = [];

        foreach ($this->type->fields() as $name => $field) {
            $texts = [];

            foreach ($section === null ? [] : $section->texts as $locale => $parts) {
                if (array_key_exists($name, $parts)) {
                    $texts[(string) $locale] = $parts[$name];
                }
            }

            $values[$name] = $field->formValue($section?->values[$name] ?? null, $texts);
        }

        return $values;
    }
}
