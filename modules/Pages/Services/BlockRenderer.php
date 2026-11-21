<?php

declare(strict_types=1);

namespace Modules\Pages\Services;

use Modules\Core\Sections\RenderContext;
use Modules\Core\Sections\SectionData;
use Modules\Core\Sections\SectionRenderer;
use Modules\Core\Theme\ZoneContent;
use Modules\Pages\Sections\BlockSection;

/**
 * Shows reusable blocks (D-47-01): inside a page, every visible `pages.block` section is replaced
 * by the visible sections of its block (`expand()`, before the page's `SectionRenderer`, so each
 * one keeps its own `<section>`, options and CSP sources); in a theme zone, the blocks assigned
 * to it whose rule applies to the current page (`fillZone()`, listener of the Core event
 * `themeZone`). A draft or deleted block shows nothing. Blocks follow the visitor's language
 * (the sections' own fallback to the site's default language) and the sections' hidden state.
 */
final class BlockRenderer
{
    /** @var array<int, list<SectionData>> */
    private array $cache = [];

    public function __construct(private readonly PageBlocks $blocks = new PageBlocks())
    {
    }

    /**
     * @param list<SectionData> $sections
     *
     * @return list<SectionData>
     */
    public function expand(array $sections): array
    {
        $expanded = [];

        foreach ($sections as $section) {
            if ($section->type !== BlockSection::KEY) {
                $expanded[] = $section;

                continue;
            }

            if (! $section->hidden) {
                array_push($expanded, ...$this->sectionsOf((int) ($section->values['block'] ?? 0)));
            }
        }

        return $expanded;
    }

    /**
     * The HTML of a published block (empty for a draft or a missing block).
     */
    public function render(int $blockId, RenderContext $context): string
    {
        $sections = $this->sectionsOf($blockId);

        return $sections === [] ? '' : (new SectionRenderer())->render($sections, $context);
    }

    /**
     * Adds the blocks of a zone for the current page (`CurrentPage`).
     */
    public function fillZone(ZoneContent $content): void
    {
        $blocks = (new BlockZones())->blocksFor($content->zone, CurrentPage::id(), CurrentPage::isHome());
        $user   = auth()->loggedIn() ? auth()->user() : null;

        foreach ($blocks as $order => $blockId) {
            $html = $this->render($blockId, RenderContext::current($user)->at(100 + $order));

            if ($html !== '') {
                $content->add('<div class="kp-block" data-block="' . $blockId . '">' . $html . '</div>', $order);
            }
        }
    }

    /**
     * The visible sections of a published block (no block sections inside: they are refused).
     *
     * @return list<SectionData>
     */
    private function sectionsOf(int $blockId): array
    {
        if ($blockId <= 0) {
            return [];
        }

        if (! isset($this->cache[$blockId])) {
            $this->cache[$blockId] = $this->blocks->isPublished($blockId)
                ? array_values(array_filter(PageSections::forBlocks()->all($blockId), static fn (SectionData $one): bool => ! $one->hidden && $one->type !== BlockSection::KEY))
                : [];
        }

        return $this->cache[$blockId];
    }
}
