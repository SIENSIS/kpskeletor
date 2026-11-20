<?php

declare(strict_types=1);

namespace Modules\Core\Sections\Fields;

use Modules\Core\Sections\FieldContext;

/**
 * Yes or no (a checkbox, never translated).
 */
class BooleanField extends Field
{
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
        return false;
    }

    public function clean(mixed $posted, FieldContext $context, string $path): array
    {
        return ['value' => in_array($posted, ['1', 1, true, 'on'], true)];
    }

    public function resolve(mixed $value, array $texts, string $locale, string $default): mixed
    {
        return $value === true;
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
