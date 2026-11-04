<?php

declare(strict_types=1);

namespace Modules\Core\Tests;

use CodeIgniter\Test\CIUnitTestCase;
use Modules\Core\Config\ModuleManifest;
use Modules\Core\Modules\ManifestValidator;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * @internal
 */
final class ManifestValidatorTest extends CIUnitTestCase
{
    private string $tmp = '';

    protected function setUp(): void
    {
        parent::setUp();
        service('language')->setLocale('en');
    }

    protected function tearDown(): void
    {
        if ($this->tmp !== '' && is_dir($this->tmp)) {
            helper('filesystem');
            delete_files($this->tmp, true);
            rmdir($this->tmp);
        }

        parent::tearDown();
    }

    private function valid(): ModuleManifest
    {
        return new class () extends ModuleManifest {
            public string $name    = 'News';
            public string $version = '1.0.0';
            public array $requires = ['Core' => '^0.1'];
            public array $resources = [
                'posts'      => ['label' => 'News.res.posts', 'type' => 'crud', 'except' => ['import'], 'extra' => ['publish' => 'News.perm.publish']],
                'stats'      => ['label' => 'News.res.stats', 'type' => 'page'],
            ];
            public array $defaultGrants = [
                'admin'  => ['news-posts.*', 'news-stats.*'],
                'editor' => ['news-posts.list', 'news-posts.publish', 'news-stats.view'],
            ];
            public array $menu = [
                ['resource' => 'posts', 'label' => 'News.menu.news', 'icon' => 'bi bi-newspaper', 'order' => 20],
                ['route' => 'settings.news', 'label' => 'News.menu.settings', 'zone' => 'settings', 'permission' => 'settings.news'],
            ];
            public array $settings = [
                'perPage' => ['type' => 'int', 'default' => 20, 'label' => 'News.settings.perPage'],
            ];
            public array $backup    = ['files' => ['news']];
            public array $calendars = ['notices' => ['label' => 'News.cal.notices', 'color' => '#dc3545']];
            public array $api       = ['posts' => ['path' => 'news', 'handler' => '\Modules\News\Api\Posts::index']];
            public array $csp       = ['frame-src' => ['https://www.youtube-nocookie.com']];
            public array $outboundHosts = ['api.example.com'];
        };
    }

    public function testValidManifestHasNoErrors(): void
    {
        $this->assertSame([], (new ManifestValidator())->validate($this->valid(), '/modules/News'));
    }

    /**
     * @return iterable<string, array{0: callable(ModuleManifest): void, 1: string}>
     */
    public static function invalidManifests(): iterable
    {
        yield 'name empty' => [static function (ModuleManifest $m): void {
            $m->name = '';
        }, '$name is required'];
        yield 'name format' => [static function (ModuleManifest $m): void {
            $m->name = 'news';
        }, '$name must be a StudlyCase'];
        yield 'name folder' => [static function (ModuleManifest $m): void {
            $m->name = 'Blog';
        }, '$name must match the folder name "News"'];
        yield 'version' => [static function (ModuleManifest $m): void {
            $m->version = '1.0';
        }, '$version must be a semantic version'];
        yield 'requires constraint' => [static function (ModuleManifest $m): void {
            $m->requires = ['Core' => 'latest'];
        }, "\$requires['Core'] has an invalid version constraint"];
        yield 'repository' => [static function (ModuleManifest $m): void {
            $m->repository = 'http://example.com/x';
        }, '$repository must be'];
        yield 'resource key' => [static function (ModuleManifest $m): void {
            $m->resources['Bad-Key'] = ['label' => 'x', 'type' => 'page'];
        }, "\$resources['Bad-Key'] has an invalid key"];
        yield 'resource type' => [static function (ModuleManifest $m): void {
            $m->resources['stats']['type'] = 'screen';
        }, "\$resources['stats']['type'] must be one of: crud, page"];
        yield 'resource label' => [static function (ModuleManifest $m): void {
            unset($m->resources['stats']['label']);
        }, "\$resources['stats']['label'] is required"];
        yield 'resource unknown key' => [static function (ModuleManifest $m): void {
            $m->resources['stats']['color'] = 'red';
        }, "\$resources['stats'] has an unknown key \"color\""];
        yield 'except on page' => [static function (ModuleManifest $m): void {
            $m->resources['stats']['except'] = ['list'];
        }, "\$resources['stats']['except'] is only allowed for crud"];
        yield 'except operation' => [static function (ModuleManifest $m): void {
            $m->resources['posts']['except'] = ['fly'];
        }, "\$resources['posts']['except'] must be one of"];
        yield 'extra operation' => [static function (ModuleManifest $m): void {
            $m->resources['posts']['extra'] = ['Pub-lish' => 'x'];
        }, "\$resources['posts']['extra'] has an invalid operation name"];
        yield 'name override in multi-resource module' => [static function (ModuleManifest $m): void {
            $m->resources['stats']['name'] = 'stats';
        }, "\$resources['stats']['name'] can only be set by Core"];
        yield 'grant of another module' => [static function (ModuleManifest $m): void {
            $m->defaultGrants['editor'][] = 'users-users.edit';
        }, "\$defaultGrants['editor'] grants \"users-users.edit\""];
        yield 'grant group name' => [static function (ModuleManifest $m): void {
            $m->defaultGrants['Editors!'] = [];
        }, "\$defaultGrants['Editors!'] has an invalid group name"];
        yield 'menu resource' => [static function (ModuleManifest $m): void {
            $m->menu[0]['resource'] = 'nope';
        }, "\$menu[0]['resource'] refers to an unknown resource"];
        yield 'menu route without permission' => [static function (ModuleManifest $m): void {
            unset($m->menu[1]['permission']);
        }, "\$menu[1]['permission'] is required"];
        yield 'menu order' => [static function (ModuleManifest $m): void {
            $m->menu[0]['order'] = 'first';
        }, "\$menu[0]['order'] must be int"];
        yield 'setting type' => [static function (ModuleManifest $m): void {
            $m->settings['perPage']['type'] = 'number';
        }, "\$settings['perPage']['type'] must be one of"];
        yield 'setting default' => [static function (ModuleManifest $m): void {
            unset($m->settings['perPage']['default']);
        }, "\$settings['perPage']['default'] is required"];
        yield 'widget class' => [static function (ModuleManifest $m): void {
            $m->widgets = ['Modules\News\Widgets\Missing'];
        }, '$widgets refers to class Modules\News\Widgets\Missing'];
        yield 'tasks list' => [static function (ModuleManifest $m): void {
            (new \ReflectionProperty($m, 'tasks'))->setValue($m, ['a' => 'b']);
        }, '$tasks must be list<class-string>'];
        yield 'backup path' => [static function (ModuleManifest $m): void {
            $m->backup = ['files' => ['../etc']];
        }, "\$backup['files'] has an invalid path \"../etc\""];
        yield 'calendar color' => [static function (ModuleManifest $m): void {
            $m->calendars['notices']['color'] = 'red';
        }, "\$calendars['notices']['color'] must be a hex colour"];
        yield 'api path' => [static function (ModuleManifest $m): void {
            $m->api['posts']['path'] = '/api/v1/news';
        }, "\$api['posts']['path'] has an invalid path"];
        yield 'api handler' => [static function (ModuleManifest $m): void {
            $m->api['posts']['handler'] = 'Posts';
        }, "\$api['posts']['handler'] must be \"Class::method\""];
        yield 'csp unsafe-inline' => [static function (ModuleManifest $m): void {
            $m->csp = ['script-src' => ["'unsafe-inline'"]];
        }, "\$csp['script-src'] has a source that is not allowed: \"'unsafe-inline'\""];
        yield 'csp http' => [static function (ModuleManifest $m): void {
            $m->csp = ['img-src' => ['http://example.com']];
        }, "\$csp['img-src'] has a source that is not allowed"];
        yield 'csp directive' => [static function (ModuleManifest $m): void {
            $m->csp = ['sandbox' => []];
        }, '$csp must be one of'];
        yield 'outbound host' => [static function (ModuleManifest $m): void {
            $m->outboundHosts = ['https://api.example.com'];
        }, '$outboundHosts has an invalid host name'];
    }

    /**
     * @param callable(ModuleManifest): void $mutate
     */
    #[DataProvider('invalidManifests')]
    public function testInvalidManifestNamesTheKey(callable $mutate, string $expected): void
    {
        $manifest = $this->valid();
        $mutate($manifest);

        $errors = (new ManifestValidator())->validate($manifest, '/modules/News');

        $this->assertNotSame([], $errors);
        $this->assertStringContainsString($expected, implode("\n", $errors));
    }

    public function testReservedNamesAreRefused(): void
    {
        $manifest       = $this->valid();
        $manifest->name = 'Security';

        $errors = (new ManifestValidator())->validate($manifest, '/modules/Security');

        $this->assertContains('Security: $name "Security" is a reserved module name', $errors);
    }

    public function testCoreAndSingleResourceModulesMayOverrideThePrefix(): void
    {
        $manifest            = $this->valid();
        $manifest->resources = ['files' => ['label' => 'Files.res.files', 'type' => 'crud', 'name' => 'files']];
        $manifest->defaultGrants = ['admin' => ['files.*']];
        $manifest->menu      = [];

        $this->assertSame([], (new ManifestValidator())->validate($manifest, '/modules/News'));
        $this->assertSame('files', $manifest->permissionPrefix('files'));
    }

    public function testConfigClassesWithSpecialFileNamesAreRefused(): void
    {
        $this->tmp = sys_get_temp_dir() . '/kps-validator-' . bin2hex(random_bytes(4));
        $path      = $this->tmp . '/News';
        mkdir($path . '/Config', 0777, true);
        file_put_contents($path . '/Config/Events.php', "<?php\nnamespace Modules\\News\\Config;\nuse CodeIgniter\\Config\\BaseConfig;\nclass Events extends BaseConfig {}\n");
        file_put_contents($path . '/Config/Routes.php', "<?php\n\$routes->get('x', 'X::y');\n");

        $errors = (new ManifestValidator())->validate($this->valid(), $path);

        $this->assertCount(1, $errors);
        $this->assertStringContainsString('Config/Events.php must not be a config class', $errors[0]);
    }
}
