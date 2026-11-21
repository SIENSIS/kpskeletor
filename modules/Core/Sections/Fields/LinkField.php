<?php

declare(strict_types=1);

namespace Modules\Core\Sections\Fields;

use Modules\Core\Sections\FieldContext;
use Modules\Core\Sections\SectionLinks;
use Modules\Core\Sections\SectionValidationException;

/**
 * A link: a page or another module's link type, a route or an `https://` address / internal path
 * (`SectionLinks`, checked like menu items), with a label per language (unless `withoutLabel()`).
 *
 * Posted: `['type' => …, 'value' => [type => value], 'label' => [locale => text]]`. Stored:
 * `['type' => …, 'value' => …]` and the label per language. Resolved: `['href', 'label',
 * 'external']`, or null when there is no link or its target is gone.
 */
class LinkField extends Field
{
    public const LABEL_MAX = 80;

    protected bool $withLabel = true;

    public function type(): string
    {
        return 'link';
    }

    public function withoutLabel(): static
    {
        $this->withLabel = false;

        return $this;
    }

    public function hasLabel(): bool
    {
        return $this->withLabel;
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

        $type   = $this->string($posted['type'] ?? null, $path);
        $values = $posted['value'] ?? [];
        $value  = is_array($values) ? $this->string($values[$type] ?? null, $path . '.value') : $this->string($values, $path . '.value');
        $labels = $posted['label'] ?? [];

        if (! is_array($labels)) {
            throw SectionValidationException::at($path . '.label', lang('Sections.errors.invalid', [$this->getLabel()]));
        }

        $texts = [];

        foreach ($context->locales as $locale) {
            $label = $this->withLabel ? $this->string($labels[$locale] ?? null, $path . '.label.' . $locale) : '';

            if (mb_strlen($label) > self::LABEL_MAX) {
                throw SectionValidationException::at($path . '.label.' . $locale, lang('Sections.errors.tooLong', [lang('Sections.link.label'), self::LABEL_MAX]));
            }

            $texts[$locale] = $label;
        }

        if ($type === '' || $value === '') {
            if ($this->required) {
                throw SectionValidationException::at($path, lang('Sections.errors.required', [$this->getLabel()]));
            }

            return ['value' => null, 'texts' => $texts];
        }

        $error = (new SectionLinks())->error($type, $value);

        if ($error !== null) {
            throw SectionValidationException::at($path . '.value', $this->getLabel() . ': ' . $error);
        }

        if ($this->withLabel && $texts[$context->defaultLocale] === '') {
            throw SectionValidationException::at($path . '.label.' . $context->defaultLocale, lang('Sections.errors.linkLabel', [$this->getLabel(), $context->localeName($context->defaultLocale)]));
        }

        return ['value' => ['type' => $type, 'value' => $value], 'texts' => $texts];
    }

    public function resolve(mixed $value, array $texts, string $locale, string $default): mixed
    {
        if (! is_array($value) || ! is_string($value['type'] ?? null) || ! is_string($value['value'] ?? null)) {
            return null;
        }

        $href = (new SectionLinks())->href($value['type'], $value['value']);

        if ($href === null) {
            return null;
        }

        $label = is_string($texts[$locale] ?? null) && $texts[$locale] !== '' ? $texts[$locale] : (is_string($texts[$default] ?? null) ? $texts[$default] : '');
        $host  = (string) parse_url(site_url(), PHP_URL_HOST);

        return [
            'href'     => $href,
            'label'    => $label,
            'external' => strcasecmp((string) parse_url($href, PHP_URL_HOST), $host) !== 0,
        ];
    }

    public function formValue(mixed $value, array $texts): mixed
    {
        $type = is_array($value) && is_string($value['type'] ?? null) ? $value['type'] : '';

        return [
            'type'  => $type,
            'value' => $type === '' ? [] : [$type => is_string($value['value'] ?? null) ? $value['value'] : ''],
            'label' => $texts,
        ];
    }

    public function isEmptyValue(mixed $value): bool
    {
        return ! is_array($value) || ($value['type'] ?? '') === '';
    }

    public function summary(mixed $resolved): string
    {
        return is_array($resolved) && is_string($resolved['label'] ?? null) ? $resolved['label'] : '';
    }
}
