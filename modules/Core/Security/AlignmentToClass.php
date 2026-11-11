<?php

declare(strict_types=1);

namespace Modules\Core\Security;

use HTMLPurifier_AttrTransform;
use HTMLPurifier_Config;
use HTMLPurifier_Context;

/**
 * HTMLPurifier attribute transform of the Core's `HtmlSanitizer`: the alignment of a block
 * (`style="text-align: …"`, the legacy `align` attribute or the editor's `ql-align-*` classes)
 * becomes one of the classes `text-center`, `text-right`, `text-justify`; `style` attributes are
 * then dropped (the strict CSP blocks them, contradiction 25 of docs/plan.md). Left alignment is
 * the default and needs no class.
 */
final class AlignmentToClass extends HTMLPurifier_AttrTransform
{
    /** Alignment value => class kept by the sanitiser. */
    public const CLASSES = [
        'center'  => 'text-center',
        'right'   => 'text-right',
        'end'     => 'text-right',
        'justify' => 'text-justify',
    ];

    /**
     * @param array<string, string> $attr
     * @param HTMLPurifier_Config   $config
     * @param HTMLPurifier_Context  $context
     *
     * @return array<string, string>
     */
    public function transform($attr, $config, $context): array
    {
        $align = null;

        if (isset($attr['style']) && preg_match('/(?:^|;)\s*text-align\s*:\s*([a-z]+)/i', $attr['style'], $match) === 1) {
            $align = strtolower($match[1]);
        }

        if ($align === null && isset($attr['align'])) {
            $align = strtolower(trim($attr['align']));
        }

        $classes = isset($attr['class']) ? preg_split('/\s+/', trim($attr['class'])) : [];
        $kept    = [];

        foreach ($classes === false ? [] : $classes as $class) {
            if (preg_match('/^(?:ql-align|text)-([a-z]+)$/', $class, $match) === 1) {
                $align ??= $match[1];

                continue;
            }

            $kept[] = $class;
        }

        unset($attr['style'], $attr['align']);

        if ($align !== null && isset(self::CLASSES[$align])) {
            $kept[] = self::CLASSES[$align];
        }

        $kept = array_values(array_unique(array_filter($kept, static fn (string $class): bool => $class !== '')));

        if ($kept === []) {
            unset($attr['class']);
        } else {
            $attr['class'] = implode(' ', $kept);
        }

        return $attr;
    }
}
