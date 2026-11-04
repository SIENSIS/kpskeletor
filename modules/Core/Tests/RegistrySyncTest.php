<?php

declare(strict_types=1);

namespace Modules\Core\Tests;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Config\Services;
use Modules\Core\Modules\SyncFailedException;
use Modules\Core\Permissions\PermissionCatalogue;
use Modules\Core\Registry\DeclarationRegistry;
use Modules\Core\Settings\SettingCaster;
use Modules\Core\Settings\SettingsRegistry;

/**
 * Steps 4–6 of modules:sync: permission catalogue (+ --prune), default grants on first install,
 * settings definitions with their defaults, and the other declarations.
 *
 * @internal
 */
final class RegistrySyncTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use CleanDatabaseTrait;
    use ModuleFixturesTrait;

    protected $migrate   = false;
    protected $refresh   = false;
    protected $namespace = null;

    private const BODY = <<<'PHP'
        public array $resources = [
                'items' => ['label' => '{{Name}}.res.items', 'type' => 'crud', 'except' => ['import'], 'extra' => ['publish' => '{{Name}}.perm.publish']],
                'stats' => ['label' => '{{Name}}.res.stats', 'type' => 'page'],
            ];
            public array $defaultGrants = [
                'admin'  => ['{{name}}-items.*', '{{name}}-stats.view'],
                'editor' => ['{{name}}-items.edit', '{{name}}-items.publish'],
            ];
            public array $settings = [
                'perPage'  => ['type' => 'int', 'default' => 20, 'rules' => 'is_natural_no_zero', 'label' => '{{Name}}.settings.perPage'],
                'notices'  => ['type' => 'bool', 'default' => true, 'label' => '{{Name}}.settings.notices'],
                'title'    => ['type' => 'string', 'default' => 'Notícies', 'label' => '{{Name}}.settings.title'],
                'ratio'    => ['type' => 'float', 'default' => 1.5, 'label' => '{{Name}}.settings.ratio'],
                'layout'   => ['type' => 'select', 'default' => 'grid', 'options' => ['grid' => 'Grid', 'list' => 'List'], 'label' => '{{Name}}.settings.layout'],
                'extra'    => ['type' => 'json', 'default' => ['a' => 1], 'label' => '{{Name}}.settings.extra'],
            ];
            public array $widgets = [\Modules\{{Name}}\Widgets\{{Name}}Widget::class];
            public array $tasks   = [\Modules\{{Name}}\Tasks\{{Name}}Task::class];
            public array $calendars = ['notices' => ['label' => '{{Name}}.cal', 'color' => '#dc3545']];
        PHP;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dropAllTables();
        service('language')->setLocale('en');
        Services::resetSingle('settings');
    }

    protected function tearDown(): void
    {
        $this->removeTempRoot();
        parent::tearDown();
    }

    public function testCatalogueGrantsAndDeclarationsAreRegistered(): void
    {
        $name   = $this->fixtureModule(self::BODY);
        $prefix = strtolower($name);
        $report = $this->synchronizer()->sync();

        $stored = (new PermissionCatalogue())->stored();
        $this->assertArrayHasKey('modules.toggle', $stored, 'Core permissions');
        $this->assertArrayHasKey("{$prefix}-items.publish", $stored);
        $this->assertArrayHasKey("{$prefix}-stats.view", $stored);
        $this->assertArrayHasKey("settings.{$prefix}", $stored);
        $this->assertArrayNotHasKey("{$prefix}-items.import", $stored);
        $this->assertContains("{$prefix}-items.list", $report->permissionsAdded);

        $catalogue = new PermissionCatalogue();
        $this->assertSame(["{$prefix}-items.*", "{$prefix}-stats.view", 'modules.list', 'modules.sync', 'modules.toggle'], $catalogue->grants('admin'));
        $this->assertSame(["{$prefix}-items.edit", "{$prefix}-items.list", "{$prefix}-items.publish", "{$prefix}-items.show"], $catalogue->grants('editor'));
        $this->assertSame(6, $report->grantsApplied[$name]);

        $registry = new DeclarationRegistry();
        $this->assertSame(["Modules\\{$name}\\Widgets\\{$name}Widget"], $registry->widgets());
        $this->assertSame(["Modules\\{$name}\\Tasks\\{$name}Task"], $registry->tasks());
        $this->assertSame('#dc3545', $registry->calendars()["{$name}.notices"]['definition']['color']);
        $this->assertTrue($report->declarationsChanged);

        $second = $this->synchronizer()->sync();
        $this->assertFalse($second->hasChanges(), 'a second sync changes nothing');
    }

    public function testDefaultGrantsAreAppliedOnlyOnFirstInstall(): void
    {
        $name   = $this->fixtureModule(self::BODY);
        $prefix = strtolower($name);
        $this->synchronizer()->sync();

        // The owner removes a default grant: a later sync must not bring it back.
        $this->db->table('group_permissions')->where('group', 'editor')->where('permission', "{$prefix}-items.publish")->delete();
        $report = $this->synchronizer()->sync();

        $this->assertSame([], $report->grantsApplied);
        $this->assertNotContains("{$prefix}-items.publish", (new PermissionCatalogue())->grants('editor'));
    }

    public function testSettingReturnsTheManifestDefaultUntilAValueIsSaved(): void
    {
        $name = $this->fixtureModule(self::BODY);
        $this->synchronizer()->sync();

        $this->assertSame(20, setting("{$name}.perPage"));
        $this->assertTrue(setting("{$name}.notices"));
        $this->assertSame('Notícies', setting("{$name}.title"));
        $this->assertSame(1.5, setting("{$name}.ratio"));
        $this->assertSame('grid', setting("{$name}.layout"));
        $this->assertSame(['a' => 1], setting("{$name}.extra"));
        $this->assertNull(setting("{$name}.undeclared"));

        setting("{$name}.perPage", 50);
        $this->assertSame(50, setting("{$name}.perPage"));

        setting()->forget("{$name}.perPage");
        $this->assertSame(20, setting("{$name}.perPage"), 'forgetting a value returns to the default');

        // A sync never overwrites a saved value.
        setting("{$name}.title", 'Avisos');
        $this->synchronizer()->sync();
        $this->assertSame('Avisos', setting("{$name}.title"));
    }

    public function testEveryDeclaredSettingReturnsItsDefault(): void
    {
        $this->fixtureModule(self::BODY);
        $this->synchronizer()->sync();

        $count = 0;

        foreach ($this->discovery()->discover() as $module) {
            foreach ($module->manifest->settings ?? [] as $key => $definition) {
                $expected = SettingCaster::cast($definition['type'], $definition['default']);
                $this->assertSame($expected, setting("{$module->name}.{$key}"), "{$module->name}.{$key}");
                $count++;
            }
        }

        $this->assertGreaterThan(0, $count);
        $this->assertCount($count, (new SettingsRegistry())->all());
    }

    public function testConfigClassPropertyKeepsPriorityOverTheManifest(): void
    {
        // A module named like a config class with the same property: the config value wins.
        $handler = new \Modules\Core\Settings\ManifestDefaultsHandler();
        $this->assertFalse($handler->has(\Config\App::class, 'baseURL'));
    }

    public function testPruneRemovesStalePermissionsOnlyAfterConfirmation(): void
    {
        $this->synchronizer()->sync();
        $this->db->table('permissions')->insert(['module' => 'Ghost', 'resource' => 'things', 'operation' => 'list', 'name' => 'ghost-things.list', 'description' => 'Core.perm.list']);
        $this->db->table('group_permissions')->insert(['group' => 'admin', 'permission' => 'ghost-things.list']);

        $report = $this->synchronizer()->sync();
        $this->assertSame(['ghost-things.list'], $report->stalePermissions, 'reported, not removed, without --prune');

        $asked  = [];
        $report = $this->synchronizer()->sync(true, static function (array $stale) use (&$asked): bool {
            $asked = $stale;

            return false;
        });
        $this->assertSame(['ghost-things.list'], $asked, '--prune asks first');
        $this->assertSame([], $report->pruned);
        $this->assertArrayHasKey('ghost-things.list', (new PermissionCatalogue())->stored());

        $report = $this->synchronizer()->sync(true, static fn (array $stale): bool => true);
        $this->assertSame(['ghost-things.list'], $report->pruned);
        $this->assertArrayNotHasKey('ghost-things.list', (new PermissionCatalogue())->stored());
        $this->assertNotContains('ghost-things.list', (new PermissionCatalogue())->grants('admin'), 'its grants go too');
    }

    public function testDuplicatePermissionStopsTheSyncBeforeWriting(): void
    {
        // B declares settings, so the catalogue gets "settings.<b>"; A (single resource named
        // "settings") generates the same name through an extra operation.
        $b = $this->fixtureModule("public array \$settings = ['size' => ['type' => 'int', 'default' => 1, 'label' => 'x']];");
        $a = $this->fixtureModule("public array \$resources = ['main' => ['label' => 'x', 'type' => 'page', 'name' => 'settings', 'extra' => ['" . strtolower($b) . "' => 'x']]];");

        try {
            $this->synchronizer()->sync();
            $this->fail('A duplicate permission must stop the synchronisation');
        } catch (SyncFailedException $e) {
            $first  = strcmp($a, $b) < 0 ? $a : $b;
            $second = $first === $a ? $b : $a;
            $this->assertStringContainsString('generates the permission "settings.' . strtolower($b) . "\", already generated by the module {$first}", implode('
', $e->errors));
            $this->assertStringContainsString($second . ':', implode('
', $e->errors));
        }

        $this->assertFalse($this->db->tableExists('permissions'), 'nothing was written');
    }

    public function testApiEntriesMustMatchARoute(): void
    {
        $routes = <<<'PHP'
            <?php
            $routes->get('api/v1/{{name}}-items', '\Modules\{{Name}}\Api\Items::index');
            PHP;
        $ok = $this->fixtureModule(
            "public array \$api = ['items' => ['path' => '{{name}}-items', 'handler' => '\\\\Modules\\\\{{Name}}\\\\Api\\\\Items::index']];",
            [],
            ['Config/Routes.php' => $routes],
        );
        $bad = $this->fixtureModule("public array \$api = ['things' => ['path' => '{{name}}-things', 'handler' => '\\\\Modules\\\\{{Name}}\\\\Api\\\\Things::index']];");

        try {
            $this->synchronizer()->sync();
            $this->fail('An $api entry without a route must stop the synchronisation');
        } catch (SyncFailedException $e) {
            $errors = implode("\n", $e->errors);
            $this->assertStringContainsString("{$bad}: \$api['things'] has no matching route: api/v1/" . strtolower($bad) . '-things', $errors);
            $this->assertStringNotContainsString($ok, $errors);
        }

        $this->assertFalse($this->db->tableExists('modules'), 'nothing was written');
    }
}
