<?php

declare(strict_types=1);

namespace Modules\Pages\Sections;

use Modules\Core\Sections\Fields\SelectField;
use Modules\Core\Sections\SectionType;

/**
 * Base of the Pages module's section types (`pages.<name>`): texts in `Pages.sectionTypes.<name>`,
 * the thumbnail `Assets/img/sections/<name>.svg` and the view `Views/sections/<name>.php`
 * (overridable in `themes/<slug>/Views/overrides/Pages/sections/`).
 */
abstract class PagesSectionType extends SectionType
{
    /** Button styles offered by the types (Bootstrap button variants of the theme's palette). */
    public const BUTTON_STYLES = [
        'primary'         => 'Pages.sectionTypes.buttons.primary',
        'light'           => 'Pages.sectionTypes.buttons.light',
        'dark'            => 'Pages.sectionTypes.buttons.dark',
        'outline-primary' => 'Pages.sectionTypes.buttons.outlinePrimary',
        'outline-light'   => 'Pages.sectionTypes.buttons.outlineLight',
        'outline-dark'    => 'Pages.sectionTypes.buttons.outlineDark',
    ];

    abstract protected function typeName(): string;

    public function key(): string
    {
        return 'pages.' . $this->typeName();
    }

    public function label(): string
    {
        return lang('Pages.sectionTypes.' . $this->typeName() . '.label');
    }

    public function description(): string
    {
        return lang('Pages.sectionTypes.' . $this->typeName() . '.description');
    }

    public function thumbnail(): ?string
    {
        helper('asset');

        return asset('pages::img/sections/' . $this->typeName() . '.svg');
    }

    /**
     * A select of button styles.
     *
     * @param list<string>|null $only the styles offered (default: all)
     */
    protected static function buttonStyle(string $name, string $label, string $default = 'primary', ?array $only = null): SelectField
    {
        $styles = $only === null ? self::BUTTON_STYLES : array_intersect_key(self::BUTTON_STYLES, array_flip($only));

        return SelectField::make($name)->label($label)->options($styles)->default($default);
    }

    /**
     * Grid columns (2 … $max).
     */
    protected static function columns(int $min, int $max, int $default): SelectField
    {
        $options = [];

        for ($i = $min; $i <= $max; $i++) {
            $options[(string) $i] = (string) $i;
        }

        return SelectField::make('columns')->label('Pages.sectionTypes.fields.columns')->options($options)->default((string) $default);
    }
}
