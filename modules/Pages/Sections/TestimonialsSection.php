<?php

declare(strict_types=1);

namespace Modules\Pages\Sections;

use Modules\Core\Sections\Fields\ImageField;
use Modules\Core\Sections\Fields\ListField;
use Modules\Core\Sections\Fields\TextareaField;
use Modules\Core\Sections\Fields\TextField;

/**
 * Testimonials `pages.testimonials`: a heading and quotes (photo, quote, name, role) in 1–3
 * columns; dark by default, like a band.
 */
final class TestimonialsSection extends PagesSectionType
{
    protected function typeName(): string
    {
        return 'testimonials';
    }

    public function icon(): string
    {
        return 'bi bi-chat-quote';
    }

    public function defaultOptions(): array
    {
        return ['background' => 'dark'];
    }

    protected function schema(): array
    {
        return [
            TextField::make('heading')->label('Pages.sectionTypes.fields.heading')->translated()->maxLength(120),
            TextareaField::make('intro')->label('Pages.sectionTypes.fields.intro')->translated()->maxLength(400),
            ListField::make('items')->label('Pages.sectionTypes.fields.testimonials')->items(1, 9)->required()->fields([
                ImageField::make('photo')->label('Pages.sectionTypes.fields.photo'),
                TextareaField::make('quote')->label('Pages.sectionTypes.fields.quote')->translated()->required()->maxLength(600),
                TextField::make('name')->label('Pages.sectionTypes.fields.name')->required()->maxLength(120),
                TextField::make('role')->label('Pages.sectionTypes.fields.role')->translated()->maxLength(120),
            ]),
            self::columns(1, 3, 3),
        ];
    }
}
