<?php

declare(strict_types=1);

namespace Modules\Core\Sections;

/**
 * A ready-made page model (D-47-02; docs/sections.md, "Page models"): "New page from a model"
 * creates a page in `sections` layout with the model's sections, filled with neutral placeholder
 * texts in every language of the site and placeholder images drawn for the skeleton. Modules
 * declare their models in the manifest key `$pageModels` and themes in `Theme.php`
 * (`$pageModels`); the `PageModelRegistry` offers those of the enabled modules and the public theme.
 *
 * Models are **generic** (D-47-01): never the texts, images or institution-specific content of a
 * real organisation (no funding notices, programme names or logos).
 */
abstract class PageModel
{
    /** "<module or theme slug>.<name>": lower case letters and digits, `-` or `_` inside the name. */
    public const KEY_PATTERN = SectionType::KEY_PATTERN;

    abstract public function key(): string;

    /** Translated name shown in the gallery of models. */
    abstract public function label(): string;

    /** One translated sentence for the gallery. */
    public function description(): string
    {
        return '';
    }

    /** The URL of a thumbnail (an SVG schematic), or null. */
    public function thumbnail(): ?string
    {
        return null;
    }

    /** A Bootstrap Icons class for the gallery. */
    public function icon(): string
    {
        return 'bi bi-layout-text-window';
    }

    /**
     * The title of the new page in a language (the default title of a page made from it).
     */
    abstract public function title(string $locale): string;

    /**
     * The sections of the new page, in order. Values and texts have the stored shape of
     * `SectionData` (texts for every language in `$context->locales`); images come from
     * `$context->image()`. Each section is validated with its type's form before it is saved.
     *
     * @return list<SectionData>
     */
    abstract public function sections(PageModelContext $context): array;

    /** The owner part of the key (`pages`). */
    final public function owner(): string
    {
        return (string) strstr($this->key(), '.', true);
    }
}
