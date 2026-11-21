<?php

declare(strict_types=1);

namespace Modules\Pages\Sections;

use Modules\Core\Sections\Fields\ImageField;
use Modules\Core\Sections\Fields\LinkField;
use Modules\Core\Sections\Fields\RichTextField;
use Modules\Core\Sections\Fields\SelectField;
use Modules\Core\Sections\Fields\TextField;

/**
 * Image and text `pages.media_text`: an image on the left or the right, a title, rich text and a
 * button (stacked on phones, the image first).
 */
final class MediaTextSection extends PagesSectionType
{
    protected function typeName(): string
    {
        return 'media_text';
    }

    public function icon(): string
    {
        return 'bi bi-layout-split';
    }

    public function thumbnail(): string
    {
        helper('asset');

        return asset('pages::img/sections/media-text.svg');
    }

    protected function schema(): array
    {
        return [
            ImageField::make('image')->label('Pages.sectionTypes.fields.image')->required(),
            SelectField::make('image_side')->label('Pages.sectionTypes.fields.imageSide')->options([
                'start' => 'Pages.sectionTypes.sides.start',
                'end'   => 'Pages.sectionTypes.sides.end',
            ]),
            TextField::make('title')->label('Pages.sectionTypes.fields.title')->translated()->maxLength(120),
            RichTextField::make('text')->label('Pages.sectionTypes.fields.text')->translated()->maxLength(50000),
            LinkField::make('link')->label('Pages.sectionTypes.fields.button'),
            self::buttonStyle('button_style', 'Pages.sectionTypes.fields.buttonStyle', 'primary'),
        ];
    }
}
