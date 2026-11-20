<?php

declare(strict_types=1);

namespace Modules\Core\Sections\Fields;

use Modules\Core\Sections\FieldContext;
use Modules\Core\Sections\Icons;
use Modules\Core\Sections\SectionValidationException;

/**
 * An icon of the vendored Bootstrap Icons, chosen in a searchable grid (`core/sections/icon-field`).
 * Stored as `bi-<name>`; never translated.
 */
class IconField extends Field
{
    public function type(): string
    {
        return 'icon';
    }

    public function translated(bool $translated = true): static
    {
        return $this;
    }

    public function clean(mixed $posted, FieldContext $context, string $path): array
    {
        $icon = $this->string($posted, $path);
        $icon = str_starts_with($icon, 'bi bi-') ? substr($icon, 3) : $icon;

        if ($icon !== '' && ! Icons::exists($icon)) {
            throw SectionValidationException::at($path, lang('Sections.errors.icon', [$this->getLabel()]));
        }

        if ($icon === '' && $this->required) {
            throw SectionValidationException::at($path, lang('Sections.errors.required', [$this->getLabel()]));
        }

        return ['value' => $icon];
    }

    public function resolve(mixed $value, array $texts, string $locale, string $default): mixed
    {
        return is_string($value) && preg_match(Icons::PATTERN, $value) === 1 ? $value : '';
    }

    public function summary(mixed $resolved): string
    {
        return '';
    }
}
