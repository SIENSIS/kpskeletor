<?php

declare(strict_types=1);

namespace Modules\Pages\Sections;

use CodeIgniter\Shield\Entities\User;
use Modules\Core\Sections\Fields\SelectField;
use Modules\Core\Sections\RenderContext;
use Modules\Core\Sections\SectionData;
use Modules\Pages\Services\PageBlocks;

/**
 * Reusable block `pages.block` (D-47-01): shows a block chosen from the list (`backend/pages/blocks`);
 * changing the block changes every page that shows it. The page renderer replaces this section
 * with the block's own sections (`BlockRenderer::expand()`), so `render()` gives nothing by
 * itself. Not offered inside a block (no nesting).
 */
final class BlockSection extends PagesSectionType
{
    public const KEY = 'pages.block';

    protected function typeName(): string
    {
        return 'block';
    }

    public function icon(): string
    {
        return 'bi bi-bricks';
    }

    protected function schema(): array
    {
        return [
            SelectField::make('block')->label('Pages.sectionTypes.fields.block')->help('Pages.sectionTypes.block.help')->required()
                ->optionsFrom(static fn (): array => (new PageBlocks())->options()),
        ];
    }

    public function render(SectionData $section, RenderContext $context): string
    {
        return '';
    }

    public function summary(SectionData $section, string $locale): string
    {
        $block = (new PageBlocks())->find((int) ($section->values['block'] ?? 0));

        return $block === null ? '' : (string) $block['name'];
    }

    public function warnings(SectionData $section, ?User $user = null): array
    {
        $block = (new PageBlocks())->find((int) ($section->values['block'] ?? 0));

        if ($block === null) {
            return [['message' => lang('Pages.blocks.warnings.missing')]];
        }

        return $block['status'] === 'published' ? [] : [['message' => lang('Pages.blocks.warnings.draft', [(string) $block['name']])]];
    }
}
