<?php

declare(strict_types=1);

namespace Modules\Core\Sections\Fields;

use Modules\Core\Sections\FieldContext;
use Modules\Core\Sections\SectionValidationException;

/**
 * One choice of a fixed list (value => label, a language key or plain text). The default is the
 * option given to `default()`, else the first one.
 */
class SelectField extends Field
{
    /** @var array<int|string, string> */
    protected array $options = [];

    protected ?string $default = null;

    public function type(): string
    {
        return 'select';
    }

    /**
     * @param array<int|string, string> $options value => label (numeric keys are read as strings)
     */
    public function options(array $options): static
    {
        $this->options = $options;

        return $this;
    }

    public function default(string $value): static
    {
        $this->default = $value;

        return $this;
    }

    /**
     * @return array<int|string, string> value => translated label
     */
    public function getOptions(): array
    {
        return array_map(static fn (string $label): string => lang($label), $this->options);
    }

    public function emptyValue(): mixed
    {
        if ($this->default !== null && array_key_exists($this->default, $this->options)) {
            return $this->default;
        }

        return (string) (array_key_first($this->options) ?? '');
    }

    public function clean(mixed $posted, FieldContext $context, string $path): array
    {
        return $this->cleanScalarOrTexts($posted, $context, $path, function (mixed $value, string $path, ?string $locale): string {
            $value = $value === null || $value === '' ? $this->emptyValue() : $value;

            if (! is_scalar($value) || ! array_key_exists((string) $value, $this->options)) {
                throw SectionValidationException::at($path . ($locale === null ? '' : '.' . $locale), lang('Sections.errors.choice', [$this->getLabel()]));
            }

            return (string) $value;
        });
    }

    public function resolve(mixed $value, array $texts, string $locale, string $default): mixed
    {
        $resolved = parent::resolve($value, $texts, $locale, $default);

        return is_scalar($resolved) && array_key_exists((string) $resolved, $this->options) ? (string) $resolved : $this->emptyValue();
    }

    public function isEmptyValue(mixed $value): bool
    {
        return $value === null || $value === '';
    }

    public function summary(mixed $resolved): string
    {
        return ''; // a choice says nothing about the content
    }
}
