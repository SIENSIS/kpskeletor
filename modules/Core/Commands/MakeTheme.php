<?php

declare(strict_types=1);

namespace Modules\Core\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Modules\Core\Theme\ThemeAssetPublisher;
use Modules\Core\Theme\ThemeGenerator;
use Modules\Core\Theme\ThemeValidator;

/**
 * php spark make:theme <Name> [--parent=default]
 */
final class MakeTheme extends BaseCommand
{
    protected $group       = 'Themes';
    protected $name        = 'make:theme';
    protected $description = 'Generates a theme in themes/<slug> (a child of the default theme unless --parent says otherwise).';
    protected $usage       = 'make:theme <Name> [--parent=default|<slug>|none]';

    /** @var array<string, string> */
    protected $arguments = [
        'Name' => 'Theme name in StudlyCase or a slug (e.g. Corporate, tasks-demo)',
    ];

    /** @var array<string, string> */
    protected $options = [
        '--parent' => 'Parent theme slug (default: default); "none" for a standalone theme with its own layouts',
    ];

    /**
     * @param array<int|string, string|null> $params
     *
     * @return int
     */
    public function run(array $params)
    {
        $name = trim((string) ($params[0] ?? ''));

        if ($name === '') {
            $name = trim(CLI::prompt(lang('Core.theme.make.nameMissing'), null, 'required'));
        }

        $option = ModulesSync::option($params, 'parent');
        $parent = is_string($option) ? $option : 'default';
        $parent = $parent === 'none' ? null : $parent;

        $generator = new ThemeGenerator();
        $error     = $generator->error($name, $parent);

        if ($error !== null) {
            CLI::error($error);

            return EXIT_ERROR;
        }

        $path = $generator->generate($name, $parent);
        $slug = ThemeGenerator::slug($name);

        foreach ((new ThemeValidator())->validate($slug) as $message) {
            CLI::error($message);
        }

        (new ThemeAssetPublisher())->publish($slug);

        CLI::write(lang('Core.theme.make.created', [$slug, clean_path($path)]), 'green');
        CLI::write(lang('Core.theme.make.next', [$slug]));

        return EXIT_SUCCESS;
    }
}
