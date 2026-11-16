<?php

declare(strict_types=1);

namespace Modules\Core\Modules;

use CodeIgniter\Controller;

/**
 * `make:module <Name> --crud=<table> --api` (D-19-03, session 26): the module's API resource is
 * written by kpapy's own generator (`php spark kpapy:make`) for the same table, with the CRUD's
 * resource name (`setResource('<module>-<key>')`, so one grant applies to the web and the API),
 * routed under `api/v1/<module>/<key>` with the `kpapy` filter, declared in the manifest's `$api`
 * and covered by a scope test (`Templates/ApiTest.php.tpl`). The controller is then rewritten to
 * the conventions (strict types, final class).
 */
final class ApiScaffold
{
    /**
     * Template values of `ModuleGenerator` (empty without an API).
     *
     * @return array<string, string>
     */
    public static function templateValues(string $module, ?string $table): array
    {
        if ($table === null) {
            return ['{{apiEntry}}' => '', '{{apiPath}}' => '', '{{apiResource}}' => ''];
        }

        $path = self::path($module, $table);

        return [
            '{{apiEntry}}'    => "\n        '" . CrudScaffold::resourceKey($module, $table) . "' => ['path' => '{$path}', 'handler' => '" . str_replace('\\', '\\\\', self::handler($module, $table)) . "'],\n    ",
            '{{apiPath}}'     => $path,
            '{{apiResource}}' => CrudScaffold::resource($module, $table),
        ];
    }

    /**
     * The path under `api/v1/` (`news_posts` in News → `news/posts`).
     */
    public static function path(string $module, string $table): string
    {
        return strtolower($module) . '/' . CrudScaffold::resourceKey($module, $table);
    }

    public static function controller(string $module, string $table): string
    {
        return 'Api/' . ucfirst(CrudScaffold::resourceKey($module, $table));
    }

    public static function handler(string $module, string $table): string
    {
        return '\\Modules\\' . $module . '\\Controllers\\' . str_replace('/', '\\', self::controller($module, $table)) . '::index';
    }

    /**
     * The arguments of `php spark kpapy:make` for the module's resource.
     *
     * @return list<string>
     */
    public static function makeArguments(string $module, string $table): array
    {
        return [
            'kpapy:make', $table,
            '--namespace', 'Modules\\' . $module,
            '--controller', self::controller($module, $table),
            '--route', 'api/v1/' . self::path($module, $table),
            '--resource', CrudScaffold::resource($module, $table),
            '--filter', 'kpapy',
            '--extends', Controller::class,
        ];
    }

    /**
     * Rewrites the controller kpapy generated to the conventions.
     */
    public static function rewriteController(string $source): string
    {
        if (! str_contains($source, 'declare(strict_types=1);')) {
            $source = (string) preg_replace('/^<\?php\n/', "<?php\n\ndeclare(strict_types=1);\n", $source, 1);
        }

        return (string) preg_replace('/^class (\w+) extends/m', 'final class $1 extends', $source);
    }
}
