<?php

declare(strict_types=1);

namespace Modules\Core\Sections\Fields;

use Closure;
use Modules\Core\Sections\FieldContext;
use Modules\Core\Sections\SectionValidationException;

/**
 * One choice of a list (value => label, a language key or plain text). The default is the
 * option given to `default()`, else the first one. The list is fixed (`options()`) or read when it
 * is needed (`optionsFrom()`: e.g. the categories of a module, read from the database).
 */
class SelectField extends Field
{
    /** @var array<int|string, string> */
    protected array $options = [];

    protected ?string $default = null;

    /** @var (Closure(): array<int|string, string>)|null */
    protected ?Closure $provider = null;

    /** @var array<int|string, string>|null */
    private ?array $provided = null;

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

    /**
     * Options read when they are first needed (once per field object): value => label, plain text
     * (not a language key).
     *
     * @param Closure(): array<int|string, string> $provider
     */
    public function optionsFrom(Closure $provider): static
    {
        $this->provider = $provider;
        $this->provided = null;

        return $this;
    }

    /**
     * value => label (untranslated).
     *
     * @return array<int|string, string>
     */
    protected function allOptions(): array
    {
        if ($this->provider === null) {
            return $this->options;
        }

        return $this->provided ??= ($this->provider)();
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
        // Options read from a provider are names already (a category's name in the reader's language).
        return $this->provider !== null ? $this->allOptions() : array_map(static fn (string $label): string => lang($label), $this->options);
    }

    public function emptyValue(): mixed
    {
        if ($this->default !== null && array_key_exists($this->default, $this->allOptions())) {
            return $this->default;
        }

        return (string) (array_key_first($this->allOptions()) ?? '');
    }

    public function clean(mixed $posted, FieldContext $context, string $path): array
    {
        return $this->cleanScalarOrTexts($posted, $context, $path, function (mixed $value, string $path, ?string $locale): string {
            $value = $value === null || $value === '' ? $this->emptyValue() : $value;

            if (! is_scalar($value) || ! array_key_exists((string) $value, $this->allOptions())) {
                throw SectionValidationException::at($path . ($locale === null ? '' : '.' . $locale), lang('Sections.errors.choice', [$this->getLabel()]));
            }

            return (string) $value;
        });
    }

    public function resolve(mixed $value, array $texts, string $locale, string $default): mixed
    {
        $resolved = parent::resolve($value, $texts, $locale, $default);

        return is_scalar($resolved) && array_key_exists((string) $resolved, $this->allOptions()) ? (string) $resolved : $this->emptyValue();
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
