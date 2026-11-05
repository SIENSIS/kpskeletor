<?php

declare(strict_types=1);

namespace Modules\Core\Tests;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Config\Database;
use Kpcrudy\Generator\CrudGenerator;
use Modules\Core\Config\ModuleManifest;
use Modules\Core\Controllers\BackendController;
use Modules\Core\Modules\CrudScaffold;
use Modules\Core\Modules\ManifestValidator;
use Modules\Core\Modules\ModuleGenerator;

/**
 * `make:module <Name> --crud=<table>` (D-00-09): kpcrudy's generator output rewritten to
 * `CrudFactory`, and the module templates with a `crud` resource.
 *
 * @internal
 */
final class CrudScaffoldTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use TempModulesTrait;

    protected $migrate = false;

    protected function setUp(): void
    {
        parent::setUp();
        service('language')->setLocale('en');
    }

    protected function tearDown(): void
    {
        Database::forge()->dropTable('library_books', true);
        $this->removeTempRoot();
        parent::tearDown();
    }

    public function testNamesComeFromTheTable(): void
    {
        $this->assertSame('posts', CrudScaffold::resourceKey('News', 'news_posts'));
        $this->assertSame('newsletteritems', CrudScaffold::resourceKey('News', 'newsletter_items'));
        $this->assertSame('news', CrudScaffold::resourceKey('News', 'news_'), 'Only the prefix: kept.');
        $this->assertSame('items', CrudScaffold::resourceKey('News', 'news_2024'), 'A key starts with a letter.');
        $this->assertSame('news-posts', CrudScaffold::resource('News', 'news_posts'));
        $this->assertNull(CrudScaffold::tableError('news_posts'));
        $this->assertNotNull(CrudScaffold::tableError('News Posts'));
        $this->assertNotNull(CrudScaffold::tableError('posts;drop'));

        $this->assertSame([
            'kpcrudy:make', 'news_posts',
            '--namespace', 'Modules\News',
            '--extends', BackendController::class,
            '--controller', 'Posts',
            '--route', 'backend/news/posts',
            '--as', 'news-posts',
            '--filter', 'backend',
        ], CrudScaffold::makeArguments('News', 'news_posts'));
    }

    public function testKpcrudyOutputIsRewrittenToTheFactory(): void
    {
        $forge = Database::forge();
        $forge->addField([
            'id'    => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'title' => ['type' => 'VARCHAR', 'constraint' => 150],
            'cover' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
        ]);
        $forge->addPrimaryKey('id');
        $forge->createTable('library_books', true);

        // What `php spark kpcrudy:make library_books --namespace "Modules\Library" …` writes.
        $generator  = new CrudGenerator(db_connect());
        $info       = $generator->analyze(db_connect()->prefixTable('library_books'));
        $method     = $generator->methodCode($info, ['method' => 'index', 'subject' => 'Book', 'plural' => 'Books', 'model' => null, 'crud' => 'Crud']);
        [, $source] = $generator->controllerSource('/nonexistent/Books.php', 'Modules\Library\Controllers', 'Books', 'index', $method, ['Kpcrudy\Crud'], false, BackendController::class);
        $route      = $generator->routeLine('backend/library/books', '\Modules\Library\Controllers\Books::index', 'backend', 'library-books');

        $this->assertStringContainsString('new Crud()', $source);
        $this->assertStringContainsString("'image'", $source, 'kpcrudy guesses an image field from the name.');

        $controller = CrudScaffold::rewriteController($source, 'library-books');

        $this->assertStringContainsString('declare(strict_types=1);', $controller);
        $this->assertStringContainsString("\$crud = \$this->crud('library-books');", $controller);
        $this->assertStringContainsString('return $this->renderCrud($crud);', $controller);
        $this->assertStringContainsString('final class Books extends BackendController', $controller);
        $this->assertStringContainsString('public function index(): ResponseInterface|string', $controller);
        $this->assertStringContainsString('use CodeIgniter\HTTP\ResponseInterface;', $controller);
        $this->assertStringNotContainsString('new Crud', $controller);
        $this->assertStringNotContainsString('use Kpcrudy\Crud;', $controller);
        $this->assertStringNotContainsString("'image'", $controller, 'No kpcrudy upload fields.');
        $this->assertStringContainsString("'cover': a file field: use the Core's file picker", $controller);

        exec('php -l ' . escapeshellarg($this->write('Books.php', $controller)) . ' 2>&1', $output, $code);
        $this->assertSame(0, $code, implode("\n", $output));

        $routes = CrudScaffold::rewriteRoutes("<?php\n\n" . $route . "\n", 'library-books');
        $this->assertStringContainsString('\\Modules\\Core\\Crud\\CrudFactory::routes($routes, \'library-books\', \'backend/library/books\', \'\\Modules\\Library\\Controllers\\Books::index\');', $routes);
        $this->assertStringNotContainsString('Kpcrudy\Crud::routes', $routes);
    }

    public function testTheModuleTemplatesDeclareTheCrudResource(): void
    {
        $name  = $this->uniqueModuleName();
        $lower = strtolower($name);
        $path  = (new ModuleGenerator($this->tempRoot()))->generate($name, $lower . '_books');

        $this->assertCount(1, glob($path . '/Database/Migrations/*_Create' . ucfirst($lower) . 'Books.php') ?: []);
        $migration = (string) file_get_contents((glob($path . '/Database/Migrations/*.php') ?: [''])[0]);
        $this->assertStringContainsString("createTable('{$lower}_books')", $migration);
        $this->assertStringContainsString("dropTable('{$lower}_books', true)", $migration);

        $manifest = (string) file_get_contents($path . '/Config/Module.php');
        $this->assertStringContainsString("'books' => ['label' => '{$name}.res.books', 'type' => 'crud', 'except' => ['import']]", $manifest);
        $this->assertStringContainsString("'{$lower}-books.*'", $manifest);
        $this->assertStringContainsString("['resource' => 'books'", $manifest);

        foreach (['ca', 'es', 'en'] as $locale) {
            $lang = require $path . "/Language/{$locale}/{$name}.php";
            $this->assertSame('Books', $lang['res']['books']);
            $this->assertSame('Books', $lang['menu']['books']);
        }

        // The manifest is valid.
        service('autoloader')->addNamespace('Modules\\' . $name, $path);
        $class = "Modules\\{$name}\\Config\\Module";
        $this->assertInstanceOf(ModuleManifest::class, new $class());
        $this->assertSame([], (new ManifestValidator())->validate(new $class(), $path));

        $this->expectException(\InvalidArgumentException::class);
        (new ModuleGenerator($this->tempRoot()))->generate($name . 'X', 'Bad Table');
    }

    private function write(string $file, string $content): string
    {
        $path = $this->tempRoot() . '/' . $file;
        file_put_contents($path, $content);

        return $path;
    }
}
