<?php

declare(strict_types=1);

namespace Modules\Pages\Sections;

use Modules\Core\Sections\Fields\LinkField;
use Modules\Core\Sections\Fields\TextareaField;
use Modules\Core\Sections\Fields\TextField;

/**
 * Call to action `pages.cta`: a title, a text and a button, centred, on the section's background
 * (the main colour by default).
 */
final class CtaSection extends PagesSectionType
{
    protected function typeName(): string
    {
        return 'cta';
    }

    public function icon(): string
    {
        return 'bi bi-megaphone';
    }

    public function defaultOptions(): array
    {
        return ['background' => 'primary', 'spacing' => 'l'];
    }

    protected function schema(): array
    {
        return [
            TextField::make('title')->label('Pages.sectionTypes.fields.title')->translated()->required()->maxLength(160),
            TextareaField::make('text')->label('Pages.sectionTypes.fields.text')->translated()->maxLength(600),
            LinkField::make('link')->label('Pages.sectionTypes.fields.button'),
            self::buttonStyle('button_style', 'Pages.sectionTypes.fields.buttonStyle', 'light'),
        ];
    }
}
