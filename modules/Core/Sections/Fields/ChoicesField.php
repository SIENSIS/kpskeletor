<?php

declare(strict_types=1);

namespace Modules\Core\Sections\Fields;

use Modules\Core\Sections\FieldContext;
use Modules\Core\Sections\SectionValidationException;

/**
 * Several choices of a list (checkboxes): e.g. the categories a news list shows. Stored as a list
 * of option keys in the order of the options; never translated. A key that is no longer offered
 * (a deleted category) is left out when the section is shown. `max()` limits how many may be
 * chosen; `required()` asks for at least one.
 */
class ChoicesField extends SelectField
{
    protected int $max = 50;

    public function type(): string
    {
        return 'choices';
    }

    public function max(int $max): static
    {
        $this->max = max(1, $max);

        return $this;
    }

    public function getMax(): int
    {
        return $this->max;
    }

    public function translated(bool $translated = true): static
    {
        return $this; // the same choices in every language
    }

    public function emptyValue(): mixed
    {
        return [];
    }

    public function isEmptyValue(mixed $value): bool
    {
        return $value === null || $value === [] || $value === '';
    }

    public function clean(mixed $posted, FieldContext $context, string $path): array
    {
        if ($posted === null || $posted === '') {
            $posted = [];
        }

        if (! is_array($posted)) {
            throw SectionValidationException::at($path, lang('Sections.errors.invalid', [$this->getLabel()]));
        }

        $options = $this->allOptions();
        $chosen  = [];

        foreach ($posted as $value) {
            if (! is_scalar($value) || ! array_key_exists((string) $value, $options)) {
                throw SectionValidationException::at($path, lang('Sections.errors.choice', [$this->getLabel()]));
            }

            $chosen[(string) $value] = true;
        }

        if (count($chosen) > $this->max) {
            throw SectionValidationException::at($path, lang('Sections.errors.tooMany', [$this->getLabel(), $this->max]));
        }

        if ($this->required && $chosen === []) {
            throw SectionValidationException::at($path, lang('Sections.errors.required', [$this->getLabel()]));
        }

        // The order of the options, not of the post.
        $value = array_values(array_filter(array_map('strval', array_keys($options)), static fn (string $key): bool => isset($chosen[$key])));

        return ['value' => $value];
    }

    public function resolve(mixed $value, array $texts, string $locale, string $default): mixed
    {
        if (! is_array($value)) {
            return [];
        }

        $options = $this->allOptions();

        return array_values(array_filter(array_map(static fn (mixed $key): string => is_scalar($key) ? (string) $key : '', $value), static fn (string $key): bool => $key !== '' && array_key_exists($key, $options)));
    }

    public function formValue(mixed $value, array $texts): mixed
    {
        return is_array($value) ? array_values(array_map('strval', array_filter($value, 'is_scalar'))) : [];
    }

    public function summary(mixed $resolved): string
    {
        if (! is_array($resolved) || $resolved === []) {
            return '';
        }

        $options = $this->getOptions();

        return implode(', ', array_map(static fn (string $key): string => (string) ($options[$key] ?? $key), $resolved));
    }
}
