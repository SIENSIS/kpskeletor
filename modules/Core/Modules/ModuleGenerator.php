<?php

declare(strict_types=1);

namespace Modules\Core\Modules;

use CodeIgniter\I18n\Time;
use Modules\Core\Config\Version;

/**
 * Generates an empty module from the templates in `Modules/Templates` (`make:module <Name>`):
 * manifest, routes, language files (ca, es, en), controller, view, asset folders, a migration,
 * the initial-data seeder, a widget stub, a scheduled-task stub and a test.
 */
final class ModuleGenerator
{
    /** Template => target path inside the module ({{Name}} is replaced). */
    private const FILES = [
        'Module.php.tpl'      => 'Config/Module.php',
        'Routes.php.tpl'      => 'Config/Routes.php',
        'Controller.php.tpl'  => 'Controllers/Home.php',
        'view.php.tpl'        => 'Views/index.php',
        'Language.ca.php.tpl' => 'Language/ca/{{Name}}.php',
        'Language.es.php.tpl' => 'Language/es/{{Name}}.php',
        'Language.en.php.tpl' => 'Language/en/{{Name}}.php',
        'Migration.php.tpl'   => 'Database/Migrations/{{date}}-000001_Create{{Name}}Items.php',
        'Seeder.php.tpl'      => 'Database/Seeds/{{Name}}Seeder.php',
        'Widget.php.tpl'      => 'Widgets/{{Name}}Widget.php',
        'Task.php.tpl'        => 'Tasks/{{Name}}Task.php',
        'Test.php.tpl'        => 'Tests/{{Name}}ModuleTest.php',
    ];

    /** Empty folders kept with a .gitkeep file. */
    private const FOLDERS = ['Assets/js', 'Assets/css', 'Assets/img', 'Models', 'Entities', 'Services', 'Notifications'];

    private readonly string $root;

    public function __construct(?string $root = null)
    {
        $this->root = rtrim($root ?? ROOTPATH . 'modules', '/\\');
    }

    /**
     * Problems with a module name, or null when it can be created.
     */
    public function nameError(string $name): ?string
    {
        if (preg_match('/^[A-Z][A-Za-z0-9]*$/', $name) !== 1) {
            return lang('Core.make.invalidName', [$name]);
        }

        if (in_array($name, ManifestValidator::RESERVED_NAMES, true)) {
            return lang('Core.make.reserved', [$name]);
        }

        if (is_dir($this->root . '/' . $name)) {
            return lang('Core.make.exists', [$name]);
        }

        return null;
    }

    /**
     * Creates the module folder.
     *
     * @return string The module path
     *
     * @throws \InvalidArgumentException when the name is invalid or the module exists
     */
    public function generate(string $name): string
    {
        $error = $this->nameError($name);

        if ($error !== null) {
            throw new \InvalidArgumentException($error);
        }

        [$major, $minor] = explode('.', Version::CORE);

        $replace = [
            '{{Name}}'           => $name,
            '{{name}}'           => strtolower($name),
            '{{date}}'           => Time::now()->format('Y-m-d'),
            '{{coreConstraint}}' => '^' . $major . '.' . $minor,
        ];

        $path = $this->root . '/' . $name;

        foreach (self::FILES as $template => $target) {
            $content = (string) file_get_contents(__DIR__ . '/Templates/' . $template);
            $this->write($path . '/' . strtr($target, $replace), strtr($content, $replace));
        }

        foreach (self::FOLDERS as $folder) {
            $this->write($path . '/' . $folder . '/.gitkeep', '');
        }

        return $path;
    }

    private function write(string $file, string $content): void
    {
        $dir = dirname($file);

        if (! is_dir($dir) && ! mkdir($dir, 0775, true) && ! is_dir($dir)) {
            throw new \RuntimeException(lang('Core.sync.namespacesNotWritable', [$dir]));
        }

        if (file_put_contents($file, $content) === false) {
            throw new \RuntimeException(lang('Core.sync.namespacesNotWritable', [$file]));
        }
    }
}
