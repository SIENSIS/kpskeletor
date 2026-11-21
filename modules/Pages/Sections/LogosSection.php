<?php

declare(strict_types=1);

namespace Modules\Pages\Sections;

use Modules\Core\Sections\Fields\BooleanField;
use Modules\Core\Sections\Fields\ImageField;
use Modules\Core\Sections\Fields\LinkField;
use Modules\Core\Sections\Fields\ListField;
use Modules\Core\Sections\Fields\TextareaField;
use Modules\Core\Sections\Fields\TextField;

/**
 * Logos `pages.logos`: a strip of logos (partners, certifications), each with its alternative
 * text (required: it names the organisation) and an optional link; optionally in grey until
 * hovered or focused.
 */
final class LogosSection extends PagesSectionType
{
    protected function typeName(): string
    {
        return 'logos';
    }

    public function icon(): string
    {
        return 'bi bi-award';
    }

    protected function schema(): array
    {
        return [
            TextField::make('heading')->label('Pages.sectionTypes.fields.heading')->translated()->maxLength(120),
            TextareaField::make('intro')->label('Pages.sectionTypes.fields.intro')->translated()->maxLength(400),
            ListField::make('logos')->label('Pages.sectionTypes.fields.logos')->items(1, 24)->required()->fields([
                ImageField::make('image')->label('Pages.sectionTypes.fields.logo')->required()->withoutDecorative(),
                LinkField::make('link')->label('Pages.sectionTypes.fields.link')->withoutLabel(),
            ]),
            BooleanField::make('grayscale')->label('Pages.sectionTypes.fields.grayscale'),
        ];
    }
}
