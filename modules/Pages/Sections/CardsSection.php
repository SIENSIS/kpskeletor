<?php

declare(strict_types=1);

namespace Modules\Pages\Sections;

use Modules\Core\Sections\Fields\ImageField;
use Modules\Core\Sections\Fields\LinkField;
use Modules\Core\Sections\Fields\ListField;
use Modules\Core\Sections\Fields\TextareaField;
use Modules\Core\Sections\Fields\TextField;

/**
 * Cards `pages.cards`: a heading, an introduction and cards (image, title, text, button) in 2–4
 * responsive columns.
 */
final class CardsSection extends PagesSectionType
{
    protected function typeName(): string
    {
        return 'cards';
    }

    public function icon(): string
    {
        return 'bi bi-card-heading';
    }

    protected function schema(): array
    {
        return [
            TextField::make('heading')->label('Pages.sectionTypes.fields.heading')->translated()->maxLength(120),
            TextareaField::make('intro')->label('Pages.sectionTypes.fields.intro')->translated()->maxLength(400),
            ListField::make('cards')->label('Pages.sectionTypes.fields.cards')->items(1, 12)->required()->fields([
                ImageField::make('image')->label('Pages.sectionTypes.fields.image'),
                TextField::make('title')->label('Pages.sectionTypes.fields.title')->translated()->required()->maxLength(120),
                TextareaField::make('text')->label('Pages.sectionTypes.fields.text')->translated()->maxLength(600),
                LinkField::make('link')->label('Pages.sectionTypes.fields.button'),
            ]),
            self::columns(2, 4, 3),
            self::buttonStyle('button_style', 'Pages.sectionTypes.fields.buttonStyle', 'primary'),
        ];
    }
}
