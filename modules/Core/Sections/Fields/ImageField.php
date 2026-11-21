<?php

declare(strict_types=1);

namespace Modules\Core\Sections\Fields;

use Modules\Core\Sections\FieldContext;
use Modules\Core\Sections\SectionValidationException;
use Modules\Core\Storage\Picker\PickerField;

/**
 * An image of the Storage service chosen with the file picker (upload or choose; only files the
 * editor may use: `FieldContext::acceptsImage()`), with an alternative text per language, required
 * in the default language unless the image is marked decorative.
 *
 * Posted: `['key' => <file key>, 'decorative' => '1', 'alt' => [locale => text]]`. Stored:
 * `['key' => …, 'decorative' => bool]` and the alt text per language. Resolved:
 * `['key', 'url', 'width', 'height', 'alt', 'decorative']`, or null without an image (or when the
 * file is gone).
 */
class ImageField extends Field
{
    public const ALT_MAX = 250;

    protected bool $decorativeAllowed = true;

    /** The image always needs its alternative text (e.g. a logo, which names an organisation). */
    public function withoutDecorative(): static
    {
        $this->decorativeAllowed = false;

        return $this;
    }

    public function allowsDecorative(): bool
    {
        return $this->decorativeAllowed;
    }

    public function type(): string
    {
        return 'image';
    }

    public function emptyValue(): mixed
    {
        return null;
    }

    public function clean(mixed $posted, FieldContext $context, string $path): array
    {
        $posted = $posted ?? [];

        if (! is_array($posted)) {
            throw SectionValidationException::at($path, lang('Sections.errors.invalid', [$this->getLabel()]));
        }

        $key = $this->string($posted['key'] ?? null, $path);

        if ($key !== '' && (PickerField::keys($key) !== [$key] || ! $context->acceptsImage($key))) {
            throw SectionValidationException::at($path, lang('Sections.errors.image', [$this->getLabel()]));
        }

        if ($key === '' && $this->required) {
            throw SectionValidationException::at($path, lang('Sections.errors.required', [$this->getLabel()]));
        }

        $decorative = $this->decorativeAllowed && in_array($posted['decorative'] ?? null, ['1', 1, true, 'on'], true);
        $alts       = $posted['alt'] ?? [];

        if (! is_array($alts)) {
            throw SectionValidationException::at($path . '.alt', lang('Sections.errors.invalid', [$this->getLabel()]));
        }

        $texts = [];

        foreach ($context->locales as $locale) {
            $alt = $decorative ? '' : $this->string($alts[$locale] ?? null, $path . '.alt.' . $locale);

            if (mb_strlen($alt) > self::ALT_MAX) {
                throw SectionValidationException::at($path . '.alt.' . $locale, lang('Sections.errors.tooLong', [lang('Sections.image.alt'), self::ALT_MAX]));
            }

            $texts[$locale] = $alt;
        }

        if ($key !== '' && ! $decorative && $texts[$context->defaultLocale] === '') {
            throw SectionValidationException::at($path . '.alt.' . $context->defaultLocale, lang('Sections.errors.alt', [$this->getLabel(), $context->localeName($context->defaultLocale)]));
        }

        return ['value' => ['key' => $key, 'decorative' => $decorative], 'texts' => $texts];
    }

    public function resolve(mixed $value, array $texts, string $locale, string $default): mixed
    {
        $key = is_array($value) && is_string($value['key'] ?? null) ? $value['key'] : '';

        if ($key === '') {
            return null;
        }

        $file = service('storage')->find($key);

        if ($file === null || ! $file->isImage() || $file->disk !== 'public') {
            return null;
        }

        $decorative = ($value['decorative'] ?? false) === true;
        $alt        = '';

        if (! $decorative) {
            $alt = is_string($texts[$locale] ?? null) && $texts[$locale] !== '' ? $texts[$locale] : (is_string($texts[$default] ?? null) ? $texts[$default] : '');
        }

        return [
            'key'        => $key,
            'url'        => service('storage')->url($file),
            'width'      => $file->width,
            'height'     => $file->height,
            'alt'        => $alt,
            'decorative' => $decorative,
        ];
    }

    public function formValue(mixed $value, array $texts): mixed
    {
        return [
            'key'        => is_array($value) && is_string($value['key'] ?? null) ? $value['key'] : '',
            'decorative' => is_array($value) && ($value['decorative'] ?? false) === true ? '1' : '',
            'alt'        => $texts,
        ];
    }

    public function isEmptyValue(mixed $value): bool
    {
        return ! is_array($value) || ($value['key'] ?? '') === '';
    }

    public function summary(mixed $resolved): string
    {
        return '';
    }

    public function fileKeys(mixed $value): array
    {
        return is_array($value) && is_string($value['key'] ?? null) && $value['key'] !== '' ? [$value['key']] : [];
    }
}
