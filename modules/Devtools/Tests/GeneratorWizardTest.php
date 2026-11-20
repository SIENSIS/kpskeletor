<?php

declare(strict_types=1);

namespace Modules\Devtools\Tests;

use CodeIgniter\I18n\Time;
use Modules\Core\Generator\Definition\ModuleDefinition;
use Modules\Core\Generator\GeneratorContext;
use Modules\Core\Modules\ModuleGenerator;
use Modules\Core\Tests\TempModulesTrait;
use Modules\Devtools\Controllers\Generator;
use Modules\Devtools\Generator\Draft;
use Modules\Devtools\Generator\SparkRunner;

/**
 * The code generator's wizard (`backend/devtools/generator`, session 42): the form and its
 * buttons, validation errors, the preview (tree, new files, diffs), the JSON definition, and the
 * write: `devtools.write` + re-authentication, refused when a file changed after the preview,
 * written into a temporary modules folder with `modules:sync` and the activity log. Every lock
 * of Devtools still applies (`DevtoolsLocksTest`).
 *
 * @internal
 */
final class GeneratorWizardTest extends DevtoolsTestCase
{
    use TempModulesTrait;

    /** The fake command runner: records the commands. */
    private SparkRunner $runner;

    protected function setUp(): void
    {
        parent::setUp();
        service('language')->setLocale('en');
        $this->withDevtoolsRoutes();
        $this->fromLocalhost();

        Generator::$testContext = new GeneratorContext(
            root: $this->tempRoot(),
            manifests: ['Auth' => new \Modules\Auth\Config\Module()],
            tableExists: static fn (string $table): bool => $table === 'users',
            groups: ['superadmin', 'admin', 'editor', 'user'],
        );
        $this->runner = Generator::$testRunner = new class () extends SparkRunner {
            /** @var list<list<string>> */
            public array $commands = [];

            public function run(array $arguments, string $input = ''): array
            {
                $this->commands[] = $arguments;

                return ['status' => 0, 'output' => 'ok'];
            }
        };
    }

    protected function tearDown(): void
    {
        Generator::$testContext = null;
        Generator::$testRunner  = null;
        $this->removeTempRoot();
        parent::tearDown();
    }

    public function testTheWizardNeedsDevtoolsView(): void
    {
        $this->send('GET', backend_path('devtools/generator'))->assertRedirectTo(site_url('login'));

        $this->createUser('admin@example.com', 'admin');
        $this->login('admin@example.com');
        $this->fromLocalhost();
        $this->send('GET', backend_path('devtools/generator'))->assertStatus(403);
        $this->send('POST', backend_path('devtools/generator'), ['action' => 'addTable'])->assertStatus(403);

        $this->fromNetwork();
        $this->assertNotFound('GET', backend_path('devtools/generator'));
    }

    public function testTheButtonsBuildTheDraft(): void
    {
        $this->loginSuperadmin();
        $this->fromLocalhost();

        $page = $this->send('GET', backend_path('devtools/generator'));
        $page->assertOK();
        $page->assertSee(lang('Devtools.generator.title'));
        $this->assertStringContainsString('data-devtools-banner', (string) $page->response()->getBody());

        $this->send('POST', backend_path('devtools/generator'), ['module' => ['mode' => 'new', 'name' => 'Shop'], 'action' => 'addTable'])->assertOK();
        $draft = Draft::get();
        $this->assertSame('Shop', $draft->name);
        $this->assertCount(1, $draft->tables);
        $this->assertSame('shop_', $draft->tables[0]->name, 'A new table starts with the module prefix.');

        $data                        = $draft->toArray();
        $post                        = ['module' => ['mode' => 'new', 'name' => 'Shop'], 'tables' => $data['tables'], 'action' => 'addColumn:0'];
        $post['tables'][0]['name']   = 'shop_items';
        $this->send('POST', backend_path('devtools/generator'), $post)->assertOK();
        $this->assertCount(2, Draft::get()->tables[0]->columns);

        $this->send('POST', backend_path('devtools/generator'), ['module' => ['mode' => 'new', 'name' => 'Shop'], 'tables' => Draft::get()->toArray()['tables'], 'action' => 'removeColumn:0:1'])->assertOK();
        $this->assertCount(1, Draft::get()->tables[0]->columns);

        $this->send('POST', backend_path('devtools/generator'), ['action' => 'reset'])->assertRedirect();
        $this->assertSame('', Draft::get()->name);
    }

    public function testTheGrantsShowEachGroupsTitleAndName(): void
    {
        // The site's groups as Users › Groups shows them: a reserved group renamed.
        db_connect()->table('groups')->where('name', 'editor')->update(['title' => 'Coordinador']);
        Generator::$testContext = new GeneratorContext(root: $this->tempRoot(), tableExists: static fn (string $table): bool => $table === 'users');
        $this->loginSuperadmin();
        $this->fromLocalhost();
        Draft::put($this->definition());

        $body = html_entity_decode((string) $this->send('GET', backend_path('devtools/generator'))->response()->getBody(), ENT_QUOTES);

        $this->assertMatchesRegularExpression('#Coordinador\s*<code class="small ms-1" data-generator-group-name>editor</code>#', $body);
        $this->assertStringContainsString('data-generator-group-name>admin</code>', $body);
    }

    public function testValidationErrorsAreShownNextToTheirFields(): void
    {
        $this->loginSuperadmin();
        $this->fromLocalhost();

        $post = ['module' => ['mode' => 'new', 'name' => 'Shop'], 'action' => 'preview', 'tables' => [[
            'name'    => 'shop_items',
            'columns' => [
                ['name' => 'title', 'type' => 'string'],
                ['name' => 'owner_id', 'type' => 'integer', 'nullable' => '0', 'foreign' => ['table' => 'users', 'onDelete' => 'SET NULL']],
            ],
            'grants' => ['admin' => ['*']],
        ]]];

        $response = $this->send('POST', backend_path('devtools/generator'), $post);
        $response->assertOK();
        $body = html_entity_decode((string) $response->response()->getBody(), ENT_QUOTES);
        $this->assertStringContainsString(lang('Core.generator.errors.setNullNotNull', ['owner_id']), $body);
        $this->assertStringContainsString('tables.0.columns.1.foreign.onDelete', $body);
    }

    public function testThePreviewShowsEveryFileAndTheDefinitionCanBeDownloadedAndLoaded(): void
    {
        $this->loginSuperadmin();
        $this->fromLocalhost();
        Draft::put($this->definition());

        $page = $this->send('GET', backend_path('devtools/generator/preview'));
        $page->assertOK();
        $body = (string) $page->response()->getBody();
        $this->assertStringContainsString('modules/Shop/Controllers/Items.php', $body);
        $this->assertStringContainsString('modules/Shop/Config/Module.php', $body);
        $this->assertStringContainsString('name="fingerprint"', $body);
        $this->assertStringContainsString('shop-items.list', $body);
        $this->assertStringContainsString('CrudFactory::routes(', html_entity_decode($body, ENT_QUOTES));

        $json = $this->send('GET', backend_path('devtools/generator/definition.json'));
        $json->assertHeader('Content-Disposition', 'attachment; filename="shop.definition.json"');
        $this->assertSame($this->definition()->toArray(), ModuleDefinition::fromJson((string) $json->response()->getBody())->toArray());

        Draft::clear();
        $this->send('POST', backend_path('devtools/generator/load'), ['json' => (string) $json->response()->getBody()])->assertRedirect();
        $this->assertSame('Shop', Draft::get()->name);

        $this->send('POST', backend_path('devtools/generator/load'), ['json' => '{not json'])->assertRedirect();
        $this->assertSame('Shop', Draft::get()->name, 'A broken definition is refused.');
    }

    public function testAnExistingModuleShowsADiffOfItsChangedFiles(): void
    {
        (new ModuleGenerator($this->tempRoot()))->generate('Zoo');
        Generator::$testContext = new GeneratorContext(root: $this->tempRoot(), tableExists: static fn (string $table): bool => $table === 'users', groups: ['superadmin', 'admin', 'editor', 'user']);
        $this->loginSuperadmin();
        $this->fromLocalhost();
        Draft::put(ModuleDefinition::fromArray(['module' => ['name' => 'Zoo', 'existing' => true], 'tables' => [['name' => 'zoo_animals', 'columns' => [['name' => 'name', 'type' => 'string']]]]]));

        $body = (string) $this->send('GET', backend_path('devtools/generator/preview'))->response()->getBody();

        $this->assertStringContainsString('data-generator-diff', $body);
        $this->assertStringContainsString('+ ', $body);
        $this->assertStringContainsString(esc("'animals' => ['label' => 'Zoo.res.animals', 'type' => 'crud'"), $body);
    }

    public function testWritingNeedsDevtoolsWriteAndReauthentication(): void
    {
        Draft::put($this->definition());

        $this->loginWith('devtools.view');
        $this->fromLocalhost();
        $this->send('POST', backend_path('devtools/generator/write'), ['fingerprint' => 'x'])->assertStatus(403);
        $this->assertDirectoryDoesNotExist($this->tempRoot() . '/Shop');
    }

    public function testWritingAsksForThePasswordAgain(): void
    {
        Draft::put($this->definition());
        $this->loginSuperadmin();
        Time::setTestNow(Time::now()->addMinutes(16));

        try {
            $this->fromLocalhost();
            $this->send('POST', backend_path('devtools/generator/write'), ['fingerprint' => 'x'])->assertRedirectTo(site_url('auth/reauth'));
        } finally {
            Time::setTestNow();
        }

        $this->assertDirectoryDoesNotExist($this->tempRoot() . '/Shop');
    }

    public function testAFileChangedAfterThePreviewRefusesTheWrite(): void
    {
        (new ModuleGenerator($this->tempRoot()))->generate('Zoo');
        Generator::$testContext = new GeneratorContext(root: $this->tempRoot(), tableExists: static fn (string $table): bool => $table === 'users', groups: ['superadmin', 'admin', 'editor', 'user']);
        $this->loginSuperadmin();
        $this->fromLocalhost();
        Draft::put(ModuleDefinition::fromArray(['module' => ['name' => 'Zoo', 'existing' => true], 'tables' => [['name' => 'zoo_animals', 'columns' => [['name' => 'name', 'type' => 'string']]]]]));

        $body = (string) $this->send('GET', backend_path('devtools/generator/preview'))->response()->getBody();
        $this->assertSame(1, preg_match('/name="fingerprint" value="([0-9a-f]{64})"/', $body, $m));

        file_put_contents($this->tempRoot() . '/Zoo/Config/Routes.php', "\n// changed by hand\n", FILE_APPEND);

        $this->send('POST', backend_path('devtools/generator/write'), ['fingerprint' => $m[1]])->assertRedirectTo(backend_url('devtools/generator/preview'));
        $this->assertSame(lang('Core.generator.changedSincePreview'), $_SESSION['error'] ?? null);
        $this->assertFileDoesNotExist($this->tempRoot() . '/Zoo/Controllers/Animals.php');
        $this->assertSame([], $this->commands());
    }

    public function testThePlanIsWrittenSyncedAndLogged(): void
    {
        $this->loginSuperadmin();
        $this->fromLocalhost();
        Draft::put($this->definition());

        $body = (string) $this->send('GET', backend_path('devtools/generator/preview'))->response()->getBody();
        $this->assertSame(1, preg_match('/name="fingerprint" value="([0-9a-f]{64})"/', $body, $m));

        $write = $this->send('POST', backend_path('devtools/generator/write'), ['fingerprint' => $m[1], 'demo' => '1']);
        $this->assertNull($_SESSION['error'] ?? null);
        $write->assertRedirectTo(backend_url('devtools/generator/result'));

        $this->assertFileExists($this->tempRoot() . '/Shop/Controllers/Items.php');
        $this->assertFileExists($this->tempRoot() . '/Shop/Config/Module.php');
        $this->assertSame([['modules:sync'], ['modules:demo', 'Shop']], $this->commands());
        $this->seeInDatabase('activity_log', ['module' => 'Devtools', 'action' => 'generated']);

        $result = $this->send('GET', backend_path('devtools/generator/result'));
        $result->assertOK();
        $result->assertSee('modules/Shop/Controllers/Items.php');
    }

    /**
     * @return list<list<string>>
     */
    private function commands(): array
    {
        return property_exists($this->runner, 'commands') ? $this->runner->commands : [];
    }

    private function definition(): ModuleDefinition
    {
        return ModuleDefinition::fromArray(['module' => ['name' => 'Shop'], 'tables' => [[
            'name'     => 'shop_items',
            'demoRows' => 2,
            'columns'  => [['name' => 'title', 'type' => 'string', 'length' => 120], ['name' => 'price', 'type' => 'decimal']],
            'crud'     => ['customOperations' => [['key' => 'archive', 'reauth' => true]]],
            'api'      => ['enabled' => true],
        ]]]);
    }
}
