<?php

declare(strict_types=1);

namespace Modules\Core\Sections;

/**
 * One section as stored: its type, the non-translated values (`page_sections.data`), the
 * translated values per language (`page_section_translations.data`), the common options and
 * whether it is hidden. `SectionType::resolve()` reads it in one language.
 */
final class SectionData
{
    /**
     * @param array<string, mixed>                $values  field => non-translated part
     * @param array<string, array<string, mixed>> $texts   locale => field => translated part
     * @param array<string, mixed>                $options the common options (SectionOptions)
     */
    public function __construct(
        public readonly string $type,
        public readonly array $values = [],
        public readonly array $texts = [],
        public readonly array $options = [],
        public readonly bool $hidden = false,
        public readonly ?int $id = null,
    ) {
    }

    /**
     * A section from its stored row (`type`, `options`, `data`, `hidden`, `id`) and its
     * translation rows (locale => JSON).
     *
     * @param array<string, mixed>  $row
     * @param array<string, string> $translations locale => JSON
     */
    public static function fromRows(array $row, array $translations): self
    {
        $texts = [];

        foreach ($translations as $locale => $json) {
            $decoded = json_decode($json, true);
            $texts[(string) $locale] = is_array($decoded) ? $decoded : [];
        }

        $values  = json_decode((string) ($row['data'] ?? ''), true);
        $options = json_decode((string) ($row['options'] ?? ''), true);

        return new self(
            (string) ($row['type'] ?? ''),
            is_array($values) ? $values : [],
            $texts,
            is_array($options) ? $options : [],
            (int) ($row['hidden'] ?? 0) === 1,
            isset($row['id']) ? (int) $row['id'] : null,
        );
    }
}
