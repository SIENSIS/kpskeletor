<?php

declare(strict_types=1);

namespace Modules\Core\Sections;

use Modules\Core\Storage\Picker\PickerField;

/**
 * The common options of every section (docs/sections.md, "Common options"), all chosen from fixed
 * lists, never CSS:
 *
 * - `style`: one of the type's `styles()`;
 * - `background`: `none`, `light`, `dark`, `primary` (the theme's palette) or `image` (with
 *   `background_image`, a file key; a dark overlay keeps the text readable);
 * - `spacing`: `s`, `m`, `l` (padding above and below);
 * - `width`: `contained` (the content in the theme's container) or `full` (edge to edge);
 * - `anchor`: an id for links to the section (`#contact`), a slug.
 *
 * Whether the section is hidden is stored apart (`page_sections.hidden`).
 */
final class SectionOptions
{
    public const BACKGROUNDS = ['none', 'light', 'dark', 'primary', 'image'];

    public const SPACINGS = ['s', 'm', 'l'];

    public const WIDTHS = ['contained', 'full'];

    public const ANCHOR = '/^[a-z][a-z0-9]*(?:-[a-z0-9]+)*$/';

    public const ANCHOR_MAX = 50;

    /** Backgrounds on which text is light (the section gets `data-bs-theme="dark"`). */
    public const DARK_BACKGROUNDS = ['dark', 'primary', 'image'];

    /**
     * The options of a section with the type's defaults for what is missing or no longer valid.
     *
     * @param array<string, mixed> $options
     *
     * @return array{style: string, background: string, background_image: string, spacing: string, width: string, anchor: string}
     */
    public static function normalise(array $options, SectionType $type): array
    {
        $defaults = self::defaults($type);
        $styles   = array_keys($type->styles());

        $pick = static fn (string $key, array $allowed): string => is_string($options[$key] ?? null) && in_array($options[$key], $allowed, true) ? $options[$key] : $defaults[$key];

        $image  = is_string($options['background_image'] ?? null) && PickerField::keys($options['background_image']) === [$options['background_image']] ? $options['background_image'] : '';
        $anchor = is_string($options['anchor'] ?? null) && self::validAnchor($options['anchor']) ? $options['anchor'] : '';

        return [
            'style'            => $styles === [] ? '' : $pick('style', $styles),
            'background'       => $pick('background', $type->backgrounds()),
            'background_image' => $image,
            'spacing'          => $pick('spacing', self::SPACINGS),
            'width'            => $pick('width', self::WIDTHS),
            'anchor'           => $anchor,
        ];
    }

    /**
     * The default options of a new section of a type.
     *
     * @return array{style: string, background: string, background_image: string, spacing: string, width: string, anchor: string}
     */
    public static function defaults(SectionType $type): array
    {
        $own         = $type->defaultOptions();
        $styles      = array_keys($type->styles());
        $backgrounds = $type->backgrounds();

        $style      = (string) ($own['style'] ?? ($styles[0] ?? ''));
        $background = (string) ($own['background'] ?? 'none');
        $spacing    = (string) ($own['spacing'] ?? 'm');
        $width      = (string) ($own['width'] ?? 'contained');

        return [
            'style'            => in_array($style, $styles, true) ? $style : (string) ($styles[0] ?? ''),
            'background'       => in_array($background, $backgrounds, true) ? $background : (string) ($backgrounds[0] ?? 'none'),
            'background_image' => '',
            'spacing'          => in_array($spacing, self::SPACINGS, true) ? $spacing : 'm',
            'width'            => in_array($width, self::WIDTHS, true) ? $width : 'contained',
            'anchor'           => '',
        ];
    }

    /**
     * Validates the posted options.
     *
     * @return array{style: string, background: string, background_image: string, spacing: string, width: string, anchor: string}
     *
     * @throws SectionValidationException
     */
    public static function clean(mixed $posted, SectionType $type, FieldContext $context): array
    {
        $posted   = $posted ?? [];
        $defaults = self::defaults($type);

        if (! is_array($posted)) {
            throw SectionValidationException::at('options', lang('Sections.errors.options'));
        }

        $unknown = array_diff(array_map('strval', array_keys($posted)), array_keys($defaults));

        if ($unknown !== []) {
            throw SectionValidationException::at('options.' . reset($unknown), lang('Sections.errors.unknownField', [reset($unknown)]));
        }

        $value = static function (string $key) use ($posted, $defaults): string {
            $raw = $posted[$key] ?? null;

            if ($raw !== null && ! is_scalar($raw)) {
                throw SectionValidationException::at('options.' . $key, lang('Sections.errors.options'));
            }

            return $raw === null || $raw === '' ? $defaults[$key] : trim((string) $raw);
        };

        $styles  = array_keys($type->styles());
        $options = [
            'style'            => $value('style'),
            'background'       => $value('background'),
            'background_image' => $value('background_image'),
            'spacing'          => $value('spacing'),
            'width'            => $value('width'),
            'anchor'           => strtolower($value('anchor')),
        ];

        if ($styles === [] ? $options['style'] !== '' : ! in_array($options['style'], $styles, true)) {
            throw SectionValidationException::at('options.style', lang('Sections.errors.choice', [lang('Sections.options.style')]));
        }

        foreach (['background' => $type->backgrounds(), 'spacing' => self::SPACINGS, 'width' => self::WIDTHS] as $key => $allowed) {
            if (! in_array($options[$key], $allowed, true)) {
                throw SectionValidationException::at('options.' . $key, lang('Sections.errors.choice', [lang('Sections.options.' . $key)]));
            }
        }

        if ($options['anchor'] !== '' && ! self::validAnchor($options['anchor'])) {
            throw SectionValidationException::at('options.anchor', lang('Sections.errors.anchor'));
        }

        if ($options['background'] !== 'image') {
            $options['background_image'] = '';
        } elseif ($options['background_image'] === '') {
            throw SectionValidationException::at('options.background_image', lang('Sections.errors.backgroundImage'));
        } elseif (PickerField::keys($options['background_image']) !== [$options['background_image']] || ! $context->acceptsImage($options['background_image'])) {
            throw SectionValidationException::at('options.background_image', lang('Sections.errors.image', [lang('Sections.options.background_image')]));
        }

        return $options;
    }

    public static function validAnchor(string $anchor): bool
    {
        return strlen($anchor) <= self::ANCHOR_MAX && preg_match(self::ANCHOR, $anchor) === 1;
    }

    /**
     * The classes of the `<section>` element.
     *
     * @param array{style: string, background: string, background_image: string, spacing: string, width: string, anchor: string} $options
     */
    public static function classes(array $options, SectionType $type): string
    {
        $classes = [
            'kp-section',
            'kp-section-' . str_replace('_', '-', $type->name()),
            'kp-bg-' . $options['background'],
            'kp-space-' . $options['spacing'],
            'kp-width-' . $options['width'],
        ];

        if ($options['style'] !== '') {
            $classes[] = 'kp-style-' . $options['style'];
        }

        return implode(' ', $classes);
    }
}
