<?php

declare(strict_types=1);

namespace Modules\Core\Sections\Fields;

use Modules\Core\Sections\FieldContext;
use Modules\Core\Sections\SectionValidationException;

/**
 * A whole number between a minimum and a maximum (never translated).
 */
class NumberField extends Field
{
    protected int $min = 0;

    protected int $max = 100;

    protected ?int $default = null;

    public function type(): string
    {
        return 'number';
    }

    public function between(int $min, int $max): static
    {
        $this->min = min($min, $max);
        $this->max = max($min, $max);

        return $this;
    }

    public function default(int $default): static
    {
        $this->default = $default;

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
        return $this; // numbers are the same in every language
    }

    public function emptyValue(): mixed
    {
        return $this->default;
    }

    public function clean(mixed $posted, FieldContext $context, string $path): array
    {
        if ($posted === null || $posted === '') {
            if ($this->required && $this->default === null) {
                throw SectionValidationException::at($path, lang('Sections.errors.required', [$this->getLabel()]));
            }

            return ['value' => $this->default];
        }

        if (! is_scalar($posted) || preg_match('/^-?\d{1,9}$/', trim((string) $posted)) !== 1) {
            throw SectionValidationException::at($path, lang('Sections.errors.number', [$this->getLabel()]));
        }

        $number = (int) trim((string) $posted);

        if ($number < $this->min || $number > $this->max) {
            throw SectionValidationException::at($path, lang('Sections.errors.between', [$this->getLabel(), $this->min, $this->max]));
        }

        return ['value' => $number];
    }

    public function resolve(mixed $value, array $texts, string $locale, string $default): mixed
    {
        return is_int($value) && $value >= $this->min && $value <= $this->max ? $value : $this->default;
    }

    public function isEmptyValue(mixed $value): bool
    {
        return $value === null;
    }

    public function summary(mixed $resolved): string
    {
        return '';
    }
}
