<?php

declare(strict_types=1);

namespace Modules\Pages\Sections;

use Modules\Core\Sections\Fields\BooleanField;
use Modules\Core\Sections\Fields\ImageField;
use Modules\Core\Sections\Fields\LinkField;
use Modules\Core\Sections\Fields\ListField;
use Modules\Core\Sections\Fields\NumberField;
use Modules\Core\Sections\Fields\SelectField;
use Modules\Core\Sections\Fields\TextareaField;
use Modules\Core\Sections\Fields\TextField;

/**
 * Carousel `pages.carousel` (D-46-02): slides like the hero banner (image under an overlay,
 * title, text, button), with arrows, dots and keyboard (`pages/carousel`).
 *
 * **No automatic rotation by default.** When rotation is turned on (every 6–30 s) a pause button
 * is always shown, it stops on hover and on focus, and it never rotates with
 * `prefers-reduced-motion` (WCAG 2.2.2). Without JavaScript every slide is shown, one under
 * the other.
 */
final class CarouselSection extends PagesSectionType
{
    public const MIN_INTERVAL = 6;

    protected function typeName(): string
    {
        return 'carousel';
    }

    public function icon(): string
    {
        return 'bi bi-collection-play';
    }

    public function backgrounds(): array
    {
        return ['dark', 'primary', 'light', 'none'];
    }

    public function defaultOptions(): array
    {
        return ['background' => 'dark', 'spacing' => 's', 'width' => 'full'];
    }

    protected function schema(): array
    {
        return [
            TextField::make('label')->label('Pages.sectionTypes.fields.carouselLabel')->translated()->required()->maxLength(120)->help('Pages.sectionTypes.help.carouselLabel'),
            ListField::make('slides')->label('Pages.sectionTypes.fields.slides')->items(1, 10)->required()->fields([
                ImageField::make('image')->label('Pages.sectionTypes.fields.image')->required(),
                TextField::make('title')->label('Pages.sectionTypes.fields.title')->translated()->maxLength(120),
                TextareaField::make('text')->label('Pages.sectionTypes.fields.text')->translated()->maxLength(300),
                LinkField::make('link')->label('Pages.sectionTypes.fields.button'),
            ]),
            SelectField::make('overlay')->label('Pages.sectionTypes.fields.overlay')->options([
                'dark'    => 'Pages.sectionTypes.overlays.dark',
                'primary' => 'Pages.sectionTypes.overlays.primary',
                'light'   => 'Pages.sectionTypes.overlays.light',
            ]),
            SelectField::make('height')->label('Pages.sectionTypes.fields.height')->options([
                'm' => 'Pages.sectionTypes.heights.m',
                's' => 'Pages.sectionTypes.heights.s',
                'l' => 'Pages.sectionTypes.heights.l',
            ]),
            self::buttonStyle('button_style', 'Pages.sectionTypes.fields.buttonStyle', 'light'),
            BooleanField::make('rotate')->label('Pages.sectionTypes.fields.rotate')->help('Pages.sectionTypes.help.rotate'),
            NumberField::make('interval')->label('Pages.sectionTypes.fields.interval')->between(self::MIN_INTERVAL, 30)->default(8),
        ];
    }
}
