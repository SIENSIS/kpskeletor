<?php

declare(strict_types=1);

namespace Modules\Pages\Sections;

use Modules\Core\Sections\Fields\ImageField;
use Modules\Core\Sections\Fields\LinkField;
use Modules\Core\Sections\Fields\SelectField;
use Modules\Core\Sections\Fields\TextareaField;
use Modules\Core\Sections\Fields\TextField;

/**
 * Hero banner `pages.hero`: a background image under an overlay of the theme's palette (or a plain
 * background), title, text, up to two buttons, an optional side image (e.g. a cut-out photo),
 * height, text alignment and the bottom edge (straight or a wave drawn by the theme's CSS).
 */
final class HeroSection extends PagesSectionType
{
    protected function typeName(): string
    {
        return 'hero';
    }

    public function icon(): string
    {
        return 'bi bi-image';
    }

    public function backgrounds(): array
    {
        return ['dark', 'primary', 'light', 'none'];
    }

    public function defaultOptions(): array
    {
        return ['background' => 'dark', 'spacing' => 'm'];
    }

    protected function schema(): array
    {
        return [
            TextField::make('title')->label('Pages.sectionTypes.fields.title')->translated()->required()->maxLength(120),
            TextareaField::make('text')->label('Pages.sectionTypes.fields.text')->translated()->maxLength(400),
            LinkField::make('primary')->label('Pages.sectionTypes.fields.primaryButton'),
            self::buttonStyle('primary_style', 'Pages.sectionTypes.fields.primaryStyle', 'primary'),
            LinkField::make('secondary')->label('Pages.sectionTypes.fields.secondaryButton'),
            self::buttonStyle('secondary_style', 'Pages.sectionTypes.fields.secondaryStyle', 'outline-light'),
            ImageField::make('background')->label('Pages.sectionTypes.fields.backgroundImage')->help('Pages.sectionTypes.help.heroBackground'),
            SelectField::make('overlay')->label('Pages.sectionTypes.fields.overlay')->options([
                'dark'    => 'Pages.sectionTypes.overlays.dark',
                'primary' => 'Pages.sectionTypes.overlays.primary',
                'light'   => 'Pages.sectionTypes.overlays.light',
            ]),
            ImageField::make('side_image')->label('Pages.sectionTypes.fields.sideImage')->help('Pages.sectionTypes.help.sideImage'),
            SelectField::make('height')->label('Pages.sectionTypes.fields.height')->options([
                'm'    => 'Pages.sectionTypes.heights.m',
                's'    => 'Pages.sectionTypes.heights.s',
                'l'    => 'Pages.sectionTypes.heights.l',
                'full' => 'Pages.sectionTypes.heights.full',
            ]),
            SelectField::make('align')->label('Pages.sectionTypes.fields.align')->options([
                'start'  => 'Pages.sectionTypes.aligns.start',
                'center' => 'Pages.sectionTypes.aligns.center',
            ]),
            SelectField::make('edge')->label('Pages.sectionTypes.fields.edge')->options([
                'straight' => 'Pages.sectionTypes.edges.straight',
                'wave'     => 'Pages.sectionTypes.edges.wave',
            ]),
        ];
    }
}
