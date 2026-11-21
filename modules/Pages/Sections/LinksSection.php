<?php

declare(strict_types=1);

namespace Modules\Pages\Sections;

use Modules\Core\Sections\Fields\IconField;
use Modules\Core\Sections\Fields\ImageField;
use Modules\Core\Sections\Fields\LinkField;
use Modules\Core\Sections\Fields\ListField;
use Modules\Core\Sections\Fields\TextareaField;
use Modules\Core\Sections\Fields\TextField;

/**
 * Quick links `pages.links`: items with an icon **or** an image, a label and a link, in 2–6
 * responsive columns. Styles: `icons` (the icon over the label), `tiles` (an illustration with a
 * coloured label) and `band` (a row of icons on a coloured band, e.g. under a banner).
 */
final class LinksSection extends PagesSectionType
{
    protected function typeName(): string
    {
        return 'links';
    }

    public function icon(): string
    {
        return 'bi bi-grid-3x3-gap';
    }

    public function styles(): array
    {
        return [
            'icons' => 'Pages.sectionTypes.styles.icons',
            'tiles' => 'Pages.sectionTypes.styles.tiles',
            'band'  => 'Pages.sectionTypes.styles.band',
        ];
    }

    protected function schema(): array
    {
        return [
            TextField::make('heading')->label('Pages.sectionTypes.fields.heading')->translated()->maxLength(120),
            TextareaField::make('intro')->label('Pages.sectionTypes.fields.intro')->translated()->maxLength(400),
            ListField::make('items')->label('Pages.sectionTypes.fields.links')->items(1, 24)->required()->fields([
                IconField::make('icon')->label('Pages.sectionTypes.fields.icon'),
                ImageField::make('image')->label('Pages.sectionTypes.fields.imageInstead')->help('Pages.sectionTypes.help.linkImage'),
                TextField::make('label')->label('Pages.sectionTypes.fields.label')->translated()->required()->maxLength(60),
                LinkField::make('link')->label('Pages.sectionTypes.fields.link')->withoutLabel(),
            ]),
            self::columns(2, 6, 4),
        ];
    }
}
