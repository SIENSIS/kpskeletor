<?php

declare(strict_types=1);

namespace Modules\Pages\Sections;

use Modules\Core\Sections\Fields\RichTextField;
use Modules\Core\Sections\Fields\SelectField;
use Modules\Core\Sections\Fields\TextField;

/**
 * Text `pages.text`: an optional heading and rich text (the Core's editor, sanitised), in a
 * readable column or the whole container.
 */
final class TextSection extends PagesSectionType
{
    protected function typeName(): string
    {
        return 'text';
    }

    public function icon(): string
    {
        return 'bi bi-text-paragraph';
    }

    protected function schema(): array
    {
        return [
            TextField::make('heading')->label('Pages.sectionTypes.fields.heading')->translated()->maxLength(160),
            RichTextField::make('text')->label('Pages.sectionTypes.fields.text')->translated()->required()->maxLength(100000),
            SelectField::make('column')->label('Pages.sectionTypes.fields.column')->options([
                'narrow' => 'Pages.sectionTypes.columnsWidth.narrow',
                'wide'   => 'Pages.sectionTypes.columnsWidth.wide',
            ]),
        ];
    }
}
