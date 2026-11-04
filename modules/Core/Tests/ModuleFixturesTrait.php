<?php

declare(strict_types=1);

namespace Modules\Core\Tests;

use Modules\Core\Modules\ModuleDiscovery;
use Modules\Core\Modules\ModuleGenerator;
use Modules\Core\Modules\ModuleSynchronizer;
use Modules\Core\Modules\NamespaceFile;

/**
 * Test modules in a temporary folder: a generated module whose manifest body, routes and extra
 * files are replaced by the test.
 */
trait ModuleFixturesTrait
{
    use TempModulesTrait;

    /**
     * @param string                $body     PHP code of the manifest properties (besides name, version, requires)
     * @param array<string, string> $requires module => constraint (Core is always required)
     * @param array<string, string> $files    path inside the module => content ({{Name}} is replaced in both, {{name}} in the content)
     */
    private function fixtureModule(string $body = '', array $requires = [], array $files = []): string
    {
        $name = $this->uniqueModuleName();
        (new ModuleGenerator($this->tempRoot()))->generate($name);

        $requires = ['Core' => '^0.1'] + $requires;
        $export   = var_export($requires, true);
        $manifest = <<<PHP
            <?php

            declare(strict_types=1);

            namespace Modules\\{$name}\\Config;

            final class Module extends \\Modules\\Core\\Config\\ModuleManifest
            {
                public string \$name    = '{$name}';
                public string \$version = '0.1.0';
                public array \$requires = {$export};

                {$body}
            }
            PHP;

        $files = ['Config/Module.php' => $manifest] + $files;

        foreach ($files as $path => $content) {
            $file = $this->tempRoot() . '/' . $name . '/' . str_replace('{{Name}}', $name, $path);

            if (! is_dir(dirname($file))) {
                mkdir(dirname($file), 0777, true);
            }

            file_put_contents($file, str_replace(['{{Name}}', '{{name}}'], [$name, strtolower($name)], $content));
        }

        return $name;
    }

    private function synchronizer(): ModuleSynchronizer
    {
        return new ModuleSynchronizer(
            new ModuleDiscovery([ROOTPATH . 'modules', $this->tempRoot()]),
            new NamespaceFile($this->tempRoot() . '/ModuleNamespaces.php'),
        );
    }

    private function discovery(): ModuleDiscovery
    {
        return new ModuleDiscovery([ROOTPATH . 'modules', $this->tempRoot()]);
    }
}
