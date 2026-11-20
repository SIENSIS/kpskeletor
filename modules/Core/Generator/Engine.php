<?php

declare(strict_types=1);

namespace Modules\Core\Generator;

use CodeIgniter\I18n\Time;
use Modules\Core\Generator\Definition\ModuleDefinition;
use Modules\Core\Generator\Definition\Texts;
use Modules\Core\Modules\ModuleGenerator;

/**
 * The code generator (D-41-02): turns a `ModuleDefinition` into a `Plan` (every file to create or
 * change with its full new content) without writing anything. The same engine serves
 * `make:module` (plain modules through `ModuleGenerator`'s templates, and
 * `make:module --definition=<file.json>`) and Devtools › Code generator, which previews the plan
 * and writes it with `PlanWriter`.
 *
 * A new module starts from `make:module`'s files; each table then adds its own files
 * (`TableCode`) and its entries in the manifest, routes, language files, demo seeder and
 * `CLAUDE.md` through `SourceEditor` (nikic/php-parser, D-42-01), exactly as for an existing
 * module, so both cases share one code path. Migrations are only ever added, never edited.
 */
final class Engine
{
    public function __construct(
        private readonly GeneratorContext $context = new GeneratorContext(),
        private readonly ?CodeFormatter $formatter = null,
    ) {
    }

    /**
     * @throws \InvalidArgumentException when the definition is invalid (messages by path in `errors()`)
     * @throws GeneratorException        when an existing file cannot be changed
     */
    public function plan(ModuleDefinition $definition): Plan
    {
        $errors = (new DefinitionValidator($this->context))->validate($definition);

        if ($errors !== []) {
            throw new InvalidDefinition($errors);
        }

        $name  = $definition->name;
        $files = $definition->existing ? $this->existingFiles($name) : $this->newModuleFiles($definition);
        $notes = [];

        if ($definition->tables !== [] || $definition->description !== null || $definition->author !== '' || $definition->requires !== []) {
            $files = $this->applyTables($definition, $files, $notes);
        }

        $formatter = $this->formatter ?? new CodeFormatter($this->context->projectRoot);
        $formatted = $formatter->format(array_filter($files, static fn (?string $c): bool => $c !== null));

        if (! $formatter->available()) {
            $notes[] = lang('Core.generator.notes.noFormatter');
        }

        $planned = [];

        foreach ($formatted as $relative => $content) {
            $path    = $name . '/' . $relative;
            $disk    = $this->context->modulePath($name) . '/' . $relative;
            $planned[$path] = new PlannedFile($path, $content, is_file($disk) ? (string) file_get_contents($disk) : null);
        }

        ksort($planned);

        $notes[] = lang('Core.generator.notes.sync', [$name]);

        if (array_filter($definition->tables, static fn ($t): bool => $t->demoRows > 0) !== []) {
            $notes[] = lang('Core.generator.notes.demo', [$name]);
        }

        $notes[] = lang('Core.generator.notes.tests', [$name]);
        $notes[] = lang('Core.generator.notes.alter');

        return new Plan($definition, $planned, $notes);
    }

    /**
     * @return array<string, string>
     */
    private function newModuleFiles(ModuleDefinition $definition): array
    {
        $name   = $definition->name;
        $lower  = $definition->lower();
        $values = [];

        if ($definition->tables !== []) {
            $first  = $definition->tables[0];
            $values = [
                '{{table}}'           => $first->name,
                '{{crudClaude}}'      => '',
                '{{apiClaude}}'       => '',
                '{{crudGrantClaude}}' => '',
                '{{apiRouteClaude}}'  => array_filter($definition->tables, static fn ($t): bool => $t->api->enabled) !== [] ? '; the API routes have the `kpapy` filter' : '',
            ];
        }

        $files = (new ModuleGenerator($this->context->root))->files($name, null, false, false, $values);

        if ($definition->tables === []) {
            return $files;
        }

        foreach (array_keys($files) as $path) {
            if (preg_match('#^Database/Migrations/.*_Create' . preg_quote($name, '#') . 'Items\.php$#', $path) === 1) {
                unset($files[$path]);
            }
        }

        $files['Database/Seeds/' . $name . 'DemoSeeder.php'] = self::emptyDemoSeeder($name);

        $tables = implode(', ', array_map(static fn ($t): string => '`' . $t->name . '`' . ($t->translatedColumns() !== [] ? ' (+ `' . $t->translationTable() . '`)' : ''), $definition->tables));
        $files['CLAUDE.md'] = str_replace('- Tables: `' . $definition->tables[0]->name . '`', '- Tables: ' . $tables, $files['CLAUDE.md']);
        $files['CLAUDE.md'] = str_replace("admin `{$lower}-main.*`.", "admin `{$lower}-main.*` and the tables' grants below.", $files['CLAUDE.md']);

        return $files;
    }

    /**
     * The module's files the generator may change, as they are on disk.
     *
     * @return array<string, string>
     */
    private function existingFiles(string $name): array
    {
        $path  = $this->context->modulePath($name);
        $files = [];

        foreach (['Config/Module.php', 'Config/Routes.php', 'Language/ca/' . $name . '.php', 'Language/es/' . $name . '.php', 'Language/en/' . $name . '.php', 'Database/Seeds/' . $name . 'DemoSeeder.php', 'CLAUDE.md'] as $file) {
            if (is_file($path . '/' . $file)) {
                $files[$file] = (string) file_get_contents($path . '/' . $file);
            }
        }

        foreach (['Config/Module.php', 'Config/Routes.php'] as $required) {
            if (! isset($files[$required])) {
                throw new GeneratorException(lang('Core.generator.missingFile', [$name . '/' . $required]));
            }
        }

        foreach (Texts::LOCALES as $locale) {
            $files['Language/' . $locale . '/' . $name . '.php'] ??= "<?php\n\ndeclare(strict_types=1);\n\nreturn [\n];\n";
        }

        $files['Database/Seeds/' . $name . 'DemoSeeder.php'] ??= self::emptyDemoSeeder($name);

        return $files;
    }

    /**
     * @param array<string, string> $files
     * @param list<string>          $notes
     *
     * @return array<string, string>
     */
    private function applyTables(ModuleDefinition $definition, array $files, array &$notes): array
    {
        $editor   = new SourceEditor();
        $name     = $definition->name;
        $manifest = 'Config/Module.php';
        $seeder   = 'Database/Seeds/' . $name . 'DemoSeeder.php';
        $stamp    = Time::now()->format('Y-m-d-His');
        $sequence = 0;
        $claude   = [];

        if ($definition->description !== null) {
            $files[$manifest] = $editor->setProperty($files[$manifest], 'description', 'string', $name . '.module.description', 'version');

            foreach (Texts::LOCALES as $locale) {
                $files['Language/' . $locale . '/' . $name . '.php'] = $editor->addArrayItems($files['Language/' . $locale . '/' . $name . '.php'], '', ['module' => ['description' => $definition->description->get($locale)]]);
            }
        }

        if ($definition->author !== '') {
            $files[$manifest] = $editor->setProperty($files[$manifest], 'author', 'string', $definition->author, 'version');
        }

        if ($definition->requires !== []) {
            $files[$manifest] = $editor->addArrayItems($files[$manifest], 'requires', $definition->requires);
        }

        foreach ($definition->tables as $table) {
            $code = new TableCode($definition, $table, $this->context);

            foreach ($code->files($stamp, $sequence) as $path => $content) {
                if (isset($files[$path]) || is_file($this->context->modulePath($name) . '/' . $path)) {
                    throw new GeneratorException(lang('Core.generator.fileExists', [$name . '/' . $path]));
                }

                $files[$path] = $content;
            }

            $files[$manifest] = $editor->addArrayItems($files[$manifest], 'resources', [$table->key($name) => $code->resourceEntry()]);

            if ($code->grants() !== []) {
                $files[$manifest] = $editor->addArrayItems($files[$manifest], 'defaultGrants', $code->grants());
            }

            if (($menu = $code->menuEntry()) !== null) {
                $files[$manifest] = $editor->addArrayItems($files[$manifest], 'menu', [$menu]);
            }

            if ($code->apiEntry() !== []) {
                $files[$manifest] = $editor->addArrayItems($files[$manifest], 'api', $code->apiEntry());
            }

            if (($routes = $code->routes()) !== '') {
                $files['Config/Routes.php'] = $editor->appendCode($files['Config/Routes.php'], $routes);
            }

            foreach (Texts::LOCALES as $locale) {
                $files['Language/' . $locale . '/' . $name . '.php'] = $editor->addArrayItems($files['Language/' . $locale . '/' . $name . '.php'], '', $code->language($locale));
            }

            if ($table->demoRows > 0) {
                $demo            = $code->demoMethod();
                $files[$seeder] = $editor->addMethod($files[$seeder], $demo['method']);
                $files[$seeder] = $editor->addToMethod($files[$seeder], 'load', $demo['call']);

                if ($demo['needsFirstId'] && ! $editor->hasMethod($files[$seeder], 'firstId')) {
                    $files[$seeder] = $editor->addMethod($files[$seeder], self::firstIdMethod());
                }
            }

            $claude[] = $code->claudeLine();
        }

        if (isset($files['CLAUDE.md']) && $claude !== []) {
            $files['CLAUDE.md'] = self::claudeLines($files['CLAUDE.md'], $claude);
        }

        return $files;
    }

    /**
     * Adds lines at the end of the list under "## Resources and permissions" (or a new section).
     *
     * @param list<string> $lines
     */
    private static function claudeLines(string $content, array $lines): string
    {
        $heading = "## Resources and permissions\n";
        $at      = strpos($content, $heading);
        $text    = implode("\n", $lines);

        if ($at === false) {
            return rtrim($content) . "\n\n" . $heading . "\n" . $text . "\n";
        }

        $next = strpos($content, "\n## ", $at + strlen($heading));
        $end  = $next === false ? strlen($content) : $next;
        $body = rtrim(substr($content, 0, $end));

        return $body . "\n" . $text . "\n" . substr($content, $end);
    }

    private static function emptyDemoSeeder(string $name): string
    {
        return <<<PHP
            <?php

            declare(strict_types=1);

            namespace Modules\\{$name}\\Database\\Seeds;

            use Modules\\Core\\Demo\\DemoData;
            use Modules\\Core\\Demo\\DemoSeeder;

            /**
             * Demo data of the {$name} module, loaded with `php spark modules:demo {$name}`. Write every
             * row through \$demo so that `--remove` deletes exactly these rows.
             */
            final class {$name}DemoSeeder extends DemoSeeder
            {
                public function load(DemoData \$demo): void
                {
                }
            }

            PHP;
    }

    private static function firstIdMethod(): string
    {
        return <<<'PHP'
                /**
                 * The first id of a table (a required foreign key of the demo rows).
                 */
                private function firstId(string $table): int
                {
                    $row = db_connect()->table($table)->select('id')->orderBy('id')->get(1)->getRowArray();

                    return is_array($row) ? (int) $row['id'] : 1;
                }
            PHP;
    }
}
