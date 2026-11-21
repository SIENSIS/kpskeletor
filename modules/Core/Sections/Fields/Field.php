<?php

declare(strict_types=1);

namespace Modules\Core\Sections\Fields;

use Modules\Core\Sections\FieldContext;
use Modules\Core\Sections\SectionValidationException;

/**
 * A field of a section type's schema (docs/sections.md, "The field schema"): what the form shows,
 * how a posted value is validated on the server and how the stored value is read back.
 *
 * Values travel in three shapes:
 *
 * - **posted** (what the form sends and shows): a scalar, or `[locale => scalar]` for a
 *   translated field; composite fields (image, link, list) post an array of their parts;
 * - **stored**: `clean()` splits a posted value into a non-translated part (`value`, kept in
 *   `page_sections.data`) and one part per language (`texts`, kept in
 *   `page_section_translations.data`); a field without a part leaves the key out;
 * - **resolved** (what a section view receives): `resolve()` merges both for one language, the
 *   default language filling the gaps.
 *
 * `clean()` never trusts the posted shape: anything unexpected is refused with a message
 * (`SectionValidationException`).
 */
abstract class Field
{
    protected string $label = '';

    protected string $help = '';

    protected bool $required = false;

    protected bool $translated = false;

    final public function __construct(public readonly string $name)
    {
    }

    public static function make(string $name): static
    {
        return new static($name);
    }

    /** The control's type (`text`, `image`, `list`…): the form view `fields/<type>`. */
    abstract public function type(): string;

    /**
     * Validates a posted value.
     *
     * @return array{value?: mixed, texts?: array<string, mixed>}
     *
     * @throws SectionValidationException
     */
    abstract public function clean(mixed $posted, FieldContext $context, string $path): array;

    /** The label: a language key or plain text. */
    public function label(string $label): static
    {
        $this->label = $label;

        return $this;
    }

    /** A help text under the control: a language key or plain text. */
    public function help(string $help): static
    {
        $this->help = $help;

        return $this;
    }

    /** Required (a translated field: in the site's default language). */
    public function required(bool $required = true): static
    {
        $this->required = $required;

        return $this;
    }

    /** One value per language of the site. */
    public function translated(bool $translated = true): static
    {
        $this->translated = $translated;

        return $this;
    }

    public function getLabel(): string
    {
        return $this->label === '' ? $this->name : lang($this->label);
    }

    public function getHelp(): string
    {
        return $this->help === '' ? '' : lang($this->help);
    }

    public function isRequired(): bool
    {
        return $this->required;
    }

    public function isTranslated(): bool
    {
        return $this->translated;
    }

    /**
     * The value a view receives in one language.
     *
     * @param array<string, mixed> $texts locale => the field's part in that language
     */
    public function resolve(mixed $value, array $texts, string $locale, string $default): mixed
    {
        if (! $this->translated) {
            return $value ?? $this->emptyValue();
        }

        $text = $texts[$locale] ?? null;

        if ($this->isEmptyValue($text)) {
            $text = $texts[$default] ?? null;
        }

        return $this->isEmptyValue($text) ? $this->emptyValue() : $text;
    }

    /**
     * The posted shape of a stored value (to fill the form).
     *
     * @param array<string, mixed> $texts locale => the field's part in that language
     */
    public function formValue(mixed $value, array $texts): mixed
    {
        return $this->translated ? $texts : $value;
    }

    /** The value of an empty field. */
    public function emptyValue(): mixed
    {
        return '';
    }

    public function isEmptyValue(mixed $value): bool
    {
        return $value === null || $value === '' || $value === [];
    }

    /**
     * A short plain-text summary of a resolved value (the editor's list), or ''.
     */
    public function summary(mixed $resolved): string
    {
        return is_string($resolved) ? trim(strip_tags($resolved)) : '';
    }

    /**
     * Image keys used by a stored value (kept when the section is saved again).
     *
     * @return list<string>
     */
    public function fileKeys(mixed $value): array
    {
        return [];
    }

    /**
     * Cleans one scalar per language (translated fields) or one scalar.
     *
     * @param callable(mixed, string, ?string): mixed $one cleans one value (value, path, locale)
     *
     * @return array{value?: mixed, texts?: array<string, mixed>}
     *
     * @throws SectionValidationException
     */
    protected function cleanScalarOrTexts(mixed $posted, FieldContext $context, string $path, callable $one): array
    {
        if (! $this->translated) {
            $value = $one($posted, $path, null);

            if ($this->required && $this->isEmptyValue($value)) {
                throw SectionValidationException::at($path, lang('Sections.errors.required', [$this->getLabel()]));
            }

            return ['value' => $value];
        }

        if ($posted !== null && ! is_array($posted)) {
            throw SectionValidationException::at($path, lang('Sections.errors.invalid', [$this->getLabel()]));
        }

        $texts = [];

        foreach ($context->locales as $locale) {
            $texts[$locale] = $one($posted[$locale] ?? null, $path, $locale);
        }

        if ($this->required && $this->isEmptyValue($texts[$context->defaultLocale] ?? null)) {
            throw SectionValidationException::at($path . '.' . $context->defaultLocale, lang('Sections.errors.requiredIn', [$this->getLabel(), $context->localeName($context->defaultLocale)]));
        }

        return ['texts' => $texts];
    }

    /**
     * A posted scalar as a trimmed string without control characters (new lines kept when asked).
     *
     * @throws SectionValidationException
     */
    protected function string(mixed $value, string $path, bool $lines = false): string
    {
        if ($value === null) {
            return '';
        }

        if (! is_scalar($value)) {
            throw SectionValidationException::at($path, lang('Sections.errors.invalid', [$this->getLabel()]));
        }

        $text = (string) $value;

        if (preg_match('//u', $text) !== 1) {
            throw SectionValidationException::at($path, lang('Sections.errors.invalid', [$this->getLabel()]));
        }

        $text = str_replace("\r\n", "\n", $text);
        $text = (string) preg_replace($lines ? '/[\x00-\x09\x0B-\x1F\x7F]/u' : '/[\x00-\x1F\x7F]/u', '', $text);

        return trim($text);
    }
}
