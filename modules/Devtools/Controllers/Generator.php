<?php

declare(strict_types=1);

namespace Modules\Devtools\Controllers;

use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;
use Modules\Core\Controllers\BackendController;
use Modules\Core\Generator\Definition\ModuleDefinition;
use Modules\Core\Generator\DefinitionValidator;
use Modules\Core\Generator\Engine;
use Modules\Core\Generator\GeneratorContext;
use Modules\Core\Generator\GeneratorException;
use Modules\Core\Generator\InvalidDefinition;
use Modules\Core\Generator\Plan;
use Modules\Core\Generator\PlanWriter;
use Modules\Devtools\Generator\Diff;
use Modules\Devtools\Generator\Draft;
use Modules\Devtools\Generator\SparkRunner;
use Modules\Devtools\Generator\WizardForm;

/**
 * The code generator (`backend/devtools/generator`, D-41-02, session 42): a wizard that builds a
 * module definition (module, tables, demo data, CRUD, API, menu and permissions), previews every
 * file of its plan (new files and diffs of changed ones) and writes them. The engine is the
 * Core's (`Modules\Core\Generator\Engine`), the same as `make:module --definition`.
 *
 * Screens and the draft (`devtools.view`): the form, its buttons, the preview, loading and
 * downloading the definition as JSON. Writing (`DevtoolsRoutes::WRITE`: `devtools.write`,
 * re-authentication, rate limit) re-plans the draft and is refused when the plan's fingerprint is
 * not the previewed one (a file changed on disk); then `modules:sync`, optionally
 * `modules:demo`, and an activity entry.
 */
final class Generator extends BackendController
{
    private const MAX_JSON = 262144;

    /** Tests only: the modules folder and installation the generator sees, and the command runner. */
    public static ?GeneratorContext $testContext = null;

    public static ?SparkRunner $testRunner = null;

    public function index(): string
    {
        $definition = Draft::get();
        $errors     = $definition->name === '' && $definition->tables === [] ? [] : (new DefinitionValidator($this->context()))->validate($definition);

        return $this->form($definition, $errors);
    }

    public function update(): ResponseInterface|string
    {
        $action = (string) $this->request->getPost('action');

        if ($action === 'reset') {
            Draft::clear();

            return redirect()->to(backend_url('devtools/generator'));
        }

        $definition = WizardForm::definition($this->post(), $this->installedModules());
        $definition = WizardForm::apply($definition, $action);
        Draft::put($definition);

        $errors = (new DefinitionValidator($this->context()))->validate($definition);

        if ($action === 'preview' && $errors === []) {
            return redirect()->to(backend_url('devtools/generator/preview'));
        }

        return $this->form($definition, $errors, $action === 'preview' || $action === 'check');
    }

    public function preview(): ResponseInterface|string
    {
        $definition = Draft::get();

        try {
            $plan = (new Engine($this->context()))->plan($definition);
        } catch (InvalidDefinition) {
            return redirect()->to(backend_url('devtools/generator'));
        } catch (GeneratorException $e) {
            return $this->form($definition, ['plan' => $e->getMessage()], true);
        }

        $this->breadcrumb(lang('Devtools.title'), backend_url('devtools'))
            ->breadcrumb(lang('Devtools.generator.title'), backend_url('devtools/generator'))
            ->breadcrumb(lang('Devtools.generator.preview.title'));

        return $this->render('Modules\Devtools\Views\generator\preview', [
            'title'       => lang('Devtools.generator.preview.title'),
            'icon'        => 'bi bi-eye',
            'plan'        => $plan,
            'diffs'       => $this->diffs($plan),
            'permissions' => $this->permissions($definition),
        ]);
    }

    public function write(): RedirectResponse
    {
        $definition  = Draft::get();
        $fingerprint = (string) $this->request->getPost('fingerprint');
        $context     = $this->context();

        try {
            $plan    = (new Engine($context))->plan($definition);
            $written = (new PlanWriter($context))->write($plan, $fingerprint);
        } catch (InvalidDefinition) {
            return redirect()->to(backend_url('devtools/generator'));
        } catch (GeneratorException $e) {
            return redirect()->to(backend_url('devtools/generator/preview'))->with('error', $e->getMessage());
        }

        $log   = [lang('Devtools.generator.result.written', [count($written)])];
        $log[] = lang('Devtools.generator.result.autoload');
        $spark = $this->spark();
        $sync  = $spark->run(['modules:sync']);
        $log[] = '$ php spark modules:sync';
        $log[] = $sync['output'];

        if ($sync['status'] === 0 && $this->request->getPost('demo') === '1' && array_filter($definition->tables, static fn ($t): bool => $t->demoRows > 0) !== []) {
            $demo  = $spark->run(['modules:demo', $definition->name], "y\n");
            $log[] = '$ php spark modules:demo ' . $definition->name;
            $log[] = $demo['output'];
        }

        activity_log('Devtools', 'generated', [
            'resource' => 'devtools',
            'summary'  => $definition->name . ($definition->existing ? ' (+' : ' (') . implode(', ', array_map(static fn ($t): string => $t->name, $definition->tables)) . ')',
        ]);

        session()->setFlashdata('devtools.generator.result', [
            'module'  => $definition->name,
            'written' => $written,
            'log'     => $log,
            'ok'      => $sync['status'] === 0,
            'notes'   => $plan->notes,
        ]);

        return redirect()->to(backend_url('devtools/generator/result'));
    }

    public function result(): ResponseInterface|string
    {
        $result = session('devtools.generator.result');

        if (! is_array($result)) {
            return redirect()->to(backend_url('devtools/generator'));
        }

        $this->breadcrumb(lang('Devtools.title'), backend_url('devtools'))
            ->breadcrumb(lang('Devtools.generator.title'), backend_url('devtools/generator'))
            ->breadcrumb(lang('Devtools.generator.result.title'));

        return $this->render('Modules\Devtools\Views\generator\result', [
            'title'  => lang('Devtools.generator.result.title'),
            'icon'   => 'bi bi-check2-circle',
            'result' => $result,
        ]);
    }

    public function download(): ResponseInterface
    {
        $definition = Draft::get();
        $name       = $definition->name !== '' ? strtolower($definition->name) : 'module';

        return $this->response
            ->setHeader('Content-Type', 'application/json; charset=utf-8')
            ->setHeader('Content-Disposition', 'attachment; filename="' . $name . '.definition.json"')
            ->setHeader('Cache-Control', 'no-store')
            ->setBody($definition->toJson());
    }

    public function load(): RedirectResponse
    {
        $json = (string) $this->request->getPost('json');
        $file = $this->request->getFile('file');

        if ($file !== null && $file->isValid() && $file->getSize() <= self::MAX_JSON) {
            // Read in memory, never stored: a definition is data for the form, not an upload.
            $json = (string) file_get_contents($file->getTempName());
        }

        if (trim($json) === '' || strlen($json) > self::MAX_JSON) {
            return redirect()->to(backend_url('devtools/generator'))->with('error', lang('Devtools.generator.loadEmpty'));
        }

        try {
            Draft::put(ModuleDefinition::fromJson($json));
        } catch (\InvalidArgumentException $e) {
            return redirect()->to(backend_url('devtools/generator'))->with('error', $e->getMessage());
        }

        return redirect()->to(backend_url('devtools/generator'))->with('message', lang('Devtools.generator.loaded'));
    }

    /**
     * @param array<string, string> $errors
     */
    private function form(ModuleDefinition $definition, array $errors, bool $showErrors = false): string
    {
        $this->breadcrumb(lang('Devtools.title'), backend_url('devtools'))->breadcrumb(lang('Devtools.generator.title'));
        $context = $this->context();

        return $this->render('Modules\Devtools\Views\generator\form', [
            'title'       => lang('Devtools.generator.title'),
            'icon'        => 'bi bi-magic',
            'definition'  => $definition,
            'data'        => $definition->toArray(),
            'errors'      => $errors,
            'showErrors'  => $showErrors,
            'modules'     => $this->installedModules(),
            'groups'      => array_combine($context->groups(), array_map($context->groupTitle(...), $context->groups())),
            'tables'      => $this->databaseTables(),
            'permissions' => $this->permissions($definition),
        ]);
    }

    /**
     * Permission names each table would declare (shown in the wizard and the preview).
     *
     * @return list<list<string>>
     */
    private function permissions(ModuleDefinition $definition): array
    {
        $names = [];

        foreach ($definition->tables as $i => $table) {
            $resource = $definition->name === '' ? '…' : $table->resource($definition->name);
            $names[$i] = array_map(static fn (string $operation): string => $resource . '.' . $operation, $table->operations());
        }

        return $names;
    }

    /**
     * @return array<string, list<array{type: string, text: string}>>
     */
    private function diffs(Plan $plan): array
    {
        $diffs = [];

        foreach ($plan->files as $path => $file) {
            if ($file->status() === 'changed') {
                $diffs[$path] = Diff::lines((string) $file->original, $file->content);
            }
        }

        return $diffs;
    }

    /**
     * Installed modules and their versions (dependencies, existing modules).
     *
     * @return array<string, string>
     */
    private function installedModules(): array
    {
        $modules = [];

        foreach ($this->context()->manifests() as $name => $manifest) {
            $modules[$name] = $manifest->version;
        }

        ksort($modules);

        return $modules;
    }

    /**
     * @return list<string>
     */
    private function databaseTables(): array
    {
        try {
            $tables = db_connect()->listTables();
        } catch (\Throwable) {
            return [];
        }

        $tables = array_values(array_filter(array_map(strval(...), $tables), static fn (string $t): bool => ! str_starts_with($t, 'sqlite_')));
        sort($tables);

        return $tables;
    }

    /**
     * @return array<string, mixed>
     */
    private function post(): array
    {
        return $this->request->getPost();
    }

    private function context(): GeneratorContext
    {
        return self::$testContext ?? new GeneratorContext();
    }

    private function spark(): SparkRunner
    {
        return self::$testRunner ?? new SparkRunner();
    }
}
