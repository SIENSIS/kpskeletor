<?php

declare(strict_types=1);

namespace Modules\Core\Sections\Fields;

use Modules\Core\Sections\FieldContext;

/**
 * Yes or no (a checkbox, never translated). `default(true)` ticks it in a new section (and when
 * the value is missing).
 */
class BooleanField extends Field
{
    protected bool $default = false;

    public function default(bool $default): static
    {
        $this->default = $default;

        return $this;
    }

    public function type(): string
    {
        return 'boolean';
    }

    public function translated(bool $translated = true): static
    {
        return $this;
    }

    public function emptyValue(): mixed
    {
        return $this->default;
    }

    public function clean(mixed $posted, FieldContext $context, string $path): array
    {
        return ['value' => $posted === null ? $this->default : in_array($posted, ['1', 1, true, 'on'], true)];
    }

    public function resolve(mixed $value, array $texts, string $locale, string $default): mixed
    {
        return is_bool($value) ? $value : $this->default;
    }

    public function formValue(mixed $value, array $texts): mixed
    {
        return is_bool($value) ? $value : $this->default;
    }

    public function isEmptyValue(mixed $value): bool
    {
        return $value !== true;
    }

    public function summary(mixed $resolved): string
    {
        return '';
    }
}
