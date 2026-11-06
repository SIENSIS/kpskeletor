<?php

declare(strict_types=1);

namespace Modules\Settings\Services;

use Config\Theme as ThemeConfig;
use Modules\Core\Settings\SettingCaster;
use Modules\Core\Theme\ThemeOptions;
use Modules\Core\Theme\ThemeRegistry;
use Modules\Settings\Forms\Field;
use Throwable;

/**
 * The theme tab: which theme the back office and the public site use (`Theme.backend`,
 * `Theme.public`) and the options of one theme, stored as `Theme.<option>` with the context
 * `theme:<slug>` (D-03-02); a value equal to the theme's default is forgotten.
 */
final class ThemeSettings
{
    public function __construct(private readonly ThemeRegistry $registry = new ThemeRegistry())
    {
    }

    /**
     * Installed themes: slug => name.
     *
     * @return array<string, string>
     */
    public function themes(): array
    {
        $themes = [];

        foreach ($this->registry->slugs() as $slug) {
            try {
                $themes[$slug] = $this->registry->get($slug)->name;
            } catch (Throwable) {
                // A broken theme cannot be chosen (ThemeValidator reports it).
            }
        }

        return $themes;
    }

    /**
     * The two area selects.
     *
     * @return list<Field>
     */
    public function areaFields(bool $readOnly = false): array
    {
        $config = new ThemeConfig();
        $fields = [];

        foreach (['backend', 'public'] as $area) {
            try {
                $stored = setting('Theme.' . $area);
            } catch (Throwable) {
                $stored = null;
            }

            $fields[] = new Field(
                'Theme-' . $area,
                'Theme.' . $area,
                'select',
                'Settings.theme.' . $area,
                is_string($stored) && $stored !== '' ? $stored : $config->{$area},
                $config->{$area},
                'required',
                'Settings.theme.' . $area . 'Help',
                $this->themes(),
                $readOnly,
            );
        }

        return $fields;
    }

    /**
     * The options of a theme (merged along its parent chain).
     *
     * @return list<Field>
     */
    public function optionFields(string $slug, bool $readOnly = false): array
    {
        $options = $this->options($slug);
        $fields  = [];

        foreach ($options->definitions() as $key => $definition) {
            $type = is_string($definition['type'] ?? null) ? $definition['type'] : 'string';

            $fields[] = new Field(
                'option-' . $key,
                'Theme.' . $key,
                $type,
                is_string($definition['label'] ?? null) ? $definition['label'] : (string) $key,
                $options->get((string) $key),
                SettingCaster::cast($type, $definition['default'] ?? null),
                is_string($definition['rules'] ?? null) ? $definition['rules'] : '',
                is_string($definition['help'] ?? null) ? $definition['help'] : null,
                Field::options(is_array($definition['options'] ?? null) ? $definition['options'] : []),
                $readOnly,
            );
        }

        return $fields;
    }

    public function options(string $slug): ThemeOptions
    {
        $manifests = array_map($this->registry->get(...), $this->registry->chain($slug));

        return new ThemeOptions($slug, $manifests);
    }
}
