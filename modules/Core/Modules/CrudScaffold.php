<?php

declare(strict_types=1);

namespace Modules\Core\Modules;

use Modules\Core\Controllers\BackendController;

/**
 * `make:module <Name> --crud=<table>` (D-00-09): the module's CRUD is written by kpcrudy's own
 * generator (`php spark kpcrudy:make`) and then rewritten to the Core's conventions:
 *
 * - `\Kpcrudy\Crud::routes($routes, '<path>', '<handler>', [...])` →
 *   `\Modules\Core\Crud\CrudFactory::routes($routes, '<resource>', '<path>', '<handler>')`;
 * - `$crud = new Crud();` → `$crud = $this->crud('<resource>');`, `return $crud->render();` →
 *   `return $this->renderCrud($crud);`, strict types, a final class and the return type;
 * - kpcrudy `image`/`upload` field types are removed (they bypass the Storage service; file
 *   fields use the Core's file picker).
 *
 * The resource is `<module>-<key>`, the key being the table name without the `<module>_` prefix
 * and without underscores (`news_posts` → `news-posts`).
 */
final class CrudScaffold
{
    /**
     * Problems with a table name, or null.
     */
    public static function tableError(string $table): ?string
    {
        return preg_match('/^[a-z][a-z0-9_]*$/', $table) === 1 ? null : lang('Core.make.invalidTable', [$table]);
    }

    /**
     * The manifest resource key of a table (`news_posts` in News → `posts`).
     */
    public static function resourceKey(string $module, string $table): string
    {
        $prefix = strtolower($module) . '_';
        $key    = str_starts_with($table, $prefix) && strlen($table) > strlen($prefix) ? substr($table, strlen($prefix)) : $table;
        $key    = (string) preg_replace('/[^a-z0-9]/', '', $key);

        return preg_match('/^[a-z]/', $key) === 1 ? $key : 'items';
    }

    public static function resource(string $module, string $table): string
    {
        return strtolower($module) . '-' . self::resourceKey($module, $table);
    }

    /**
     * Template values of `ModuleGenerator` (empty without a CRUD).
     *
     * @return array<string, string>
     */
    public static function templateValues(string $module, ?string $table): array
    {
        if ($table === null) {
            return ['{{crudResource}}' => '', '{{crudGrant}}' => '', '{{crudMenu}}' => '', '{{crudLang}}' => ''];
        }

        $key   = self::resourceKey($module, $table);
        $label = ucfirst(str_replace('_', ' ', $key));

        return [
            '{{crudResource}}' => "\n        '{$key}' => ['label' => '{$module}.res.{$key}', 'type' => 'crud', 'except' => ['import']],",
            '{{crudGrant}}'    => ", '" . self::resource($module, $table) . ".*'",
            '{{crudMenu}}'     => "\n        ['resource' => '{$key}', 'label' => '{$module}.menu.{$key}', 'icon' => 'bi bi-table', 'parent' => 'main', 'order' => 10],",
            '{{crudLang}}'     => ", '{$key}' => '{$label}'",
        ];
    }

    /**
     * The arguments of `php spark kpcrudy:make` for the module's CRUD.
     *
     * @return list<string>
     */
    public static function makeArguments(string $module, string $table): array
    {
        $key = self::resourceKey($module, $table);

        return [
            'kpcrudy:make', $table,
            '--namespace', 'Modules\\' . $module,
            '--extends', BackendController::class,
            '--controller', ucfirst($key),
            '--route', 'backend/' . strtolower($module) . '/' . $key,
            '--as', self::resource($module, $table),
            '--filter', 'backend',
        ];
    }

    /**
     * Rewrites kpcrudy's route line(s) to `CrudFactory::routes()`.
     */
    public static function rewriteRoutes(string $source, string $resource): string
    {
        $pattern = '/\\\\?Kpcrudy\\\\Crud::routes\(\$routes,\s*(\'[^\']*\'),\s*(\'[^\']*\')(?:,\s*\[[^\]]*\])?\);/';

        return (string) preg_replace_callback(
            $pattern,
            static fn (array $m): string => '\\Modules\\Core\\Crud\\CrudFactory::routes($routes, ' . var_export($resource, true) . ', ' . $m[1] . ', ' . $m[2] . ');',
            $source,
        );
    }

    /**
     * Rewrites the controller kpcrudy generated to the Core's conventions.
     */
    public static function rewriteController(string $source, string $resource): string
    {
        $source = str_replace('$crud = new Crud();', '$crud = $this->crud(' . var_export($resource, true) . ');', $source);
        $source = str_replace('return $crud->render();', 'return $this->renderCrud($crud);', $source);

        $source = self::withoutUploadFields($source);

        if (preg_match('/\bCrud\b(?!::)/', (string) preg_replace('/^use Kpcrudy\\\\Crud;\n/m', '', $source)) !== 1) {
            $source = (string) preg_replace('/^use Kpcrudy\\\\Crud;\n/m', '', $source);
        }

        if (! str_contains($source, 'declare(strict_types=1);')) {
            $source = (string) preg_replace('/^<\?php\n/', "<?php\n\ndeclare(strict_types=1);\n", $source, 1);
        }

        $source = (string) preg_replace('/^class (\w+) extends/m', 'final class $1 extends', $source);
        $source = (string) preg_replace('/(public function \w+)\(\)\n/', "$1(): ResponseInterface|string\n", $source);

        if (str_contains($source, 'ResponseInterface|string') && ! str_contains($source, 'use CodeIgniter\HTTP\ResponseInterface;')) {
            $source = (string) preg_replace('/^(namespace [^;]+;\n\n)/m', "$1use CodeIgniter\\HTTP\\ResponseInterface;\n", $source, 1);
        }

        return $source;
    }

    /**
     * Replaces kpcrudy `image`/`upload` field types (they bypass the Storage service) with a
     * comment; a `;` ending the chain moves to the previous line.
     */
    private static function withoutUploadFields(string $source): string
    {
        $lines = explode("\n", $source);

        foreach ($lines as $i => $line) {
            if (preg_match("/^(\\s*)->fieldType\\(('[^']*'), '(?:image|upload)'.*\\)(;?)$/", $line, $m) !== 1) {
                continue;
            }

            $lines[$i] = $m[1] . '// ' . $m[2] . ": a file field: use the Core's file picker (Storage service), never kpcrudy uploads.";

            if ($m[3] === ';') {
                for ($j = $i - 1; $j >= 0; $j--) {
                    if (trim($lines[$j]) !== '' && ! str_starts_with(trim($lines[$j]), '//')) {
                        $lines[$j] = rtrim($lines[$j]) . ';';

                        break;
                    }
                }
            }
        }

        return implode("\n", $lines);
    }
}
