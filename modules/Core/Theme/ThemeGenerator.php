<?php

declare(strict_types=1);

namespace Modules\Core\Theme;

/**
 * Generates a theme (`make:theme <Name> [--parent=default]`): a child theme only overrides what
 * it needs; a standalone theme (`--parent=none`) gets a minimal file for each layout.
 */
final class ThemeGenerator
{
    public function __construct(private readonly ThemeRegistry $registry = new ThemeRegistry())
    {
    }

    /**
     * The slug for a theme name: "Corporate" → "corporate", "TasksDemo" → "tasks-demo"; a slug is
     * kept as it is.
     */
    public static function slug(string $name): string
    {
        return ThemeRegistry::slugFromName(trim($name));
    }

    /**
     * An error message, or null when the theme can be generated.
     */
    public function error(string $name, ?string $parent): ?string
    {
        $slug = self::slug($name);

        if (preg_match('/^[A-Za-z][A-Za-z0-9-]*$/', $name) !== 1 || ! ThemeRegistry::isValidSlug($slug)) {
            return lang('Core.theme.make.invalidName', [$name]);
        }

        if (is_dir($this->registry->path($slug))) {
            return lang('Core.theme.make.exists', [$slug]);
        }

        if ($parent !== null && ! $this->registry->exists($parent)) {
            return lang('Core.theme.make.parentMissing', [$parent]);
        }

        return null;
    }

    /**
     * @return string the theme's folder
     */
    public function generate(string $name, ?string $parent = 'default'): string
    {
        $slug   = self::slug($name);
        $studly = ThemeRegistry::studly($slug);
        $path   = $this->registry->path($slug);
        $title  = ucwords(str_replace('-', ' ', $slug));

        $replace = [
            '{{Name}}'      => $title,
            '{{Studly}}'    => $studly,
            '{{Parent}}'    => $parent === null ? 'null' : var_export($parent, true),
            '{{ParentDoc}}' => $parent === null ? '' : ', child of "' . $parent . '"',
            '{{Layouts}}'   => $parent === null ? "['" . implode("', '", ThemeManifest::LAYOUTS) . "']" : '[]',
        ];

        $files = [
            'Theme.php'                             => $this->template('Theme.php.tpl', $replace),
            'Assets/css/theme.css'                  => $this->template('theme.css.tpl', $replace),
            'Assets/js/.gitkeep'                    => '',
            'Assets/img/.gitkeep'                   => '',
            'Views/overrides/.gitkeep'              => '',
            'Views/components/.gitkeep'             => '',
            'Views/partials/.gitkeep'               => '',
            "Language/ca/Theme{$studly}.php"        => $this->template('Language.php.tpl', $replace),
            "Language/es/Theme{$studly}.php"        => $this->template('Language.php.tpl', $replace),
            "Language/en/Theme{$studly}.php"        => $this->template('Language.php.tpl', $replace),
        ];

        if ($parent === null) {
            foreach (ThemeManifest::LAYOUTS as $layout) {
                $files["Views/layouts/{$layout}.php"] = $this->template('layout.php.tpl', $replace + ['{{Layout}}' => $layout]);
            }
        } else {
            $files['Views/layouts/.gitkeep'] = '';
        }

        foreach ($files as $file => $content) {
            $target = $path . '/' . $file;

            if (! is_dir(dirname($target))) {
                mkdir(dirname($target), 0755, true);
            }

            file_put_contents($target, $content);
        }

        return $path;
    }

    /**
     * @param array<string, string> $replace
     */
    private function template(string $file, array $replace): string
    {
        return strtr((string) file_get_contents(__DIR__ . '/Templates/' . $file), $replace);
    }
}
