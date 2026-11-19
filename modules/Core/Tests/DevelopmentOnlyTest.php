<?php

declare(strict_types=1);

namespace Modules\Core\Tests;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Modules\Core\Frontend\AssetPublisher;
use Modules\Core\Models\ModuleModel;
use Modules\Core\Modules\DevelopmentOnly;
use Modules\Core\Modules\ManifestValidator;
use Modules\Core\Modules\ModuleDiscovery;
use Modules\Core\Modules\ModuleOverview;
use Modules\Core\Modules\ModuleSynchronizer;
use Modules\Core\Modules\ModuleToggleException;
use Modules\Core\Modules\ModuleToggler;
use Modules\Core\Modules\NamespaceFile;
use Modules\Core\Security\Check\CheckResult;
use Modules\Core\Security\Check\Checks\InstallationCheck;
use Modules\Core\Security\Check\SecurityReport;

/**
 * Development-only modules (manifest `$developmentOnly`, D-41-01): the manifest key, enabling
 * (modules:enable and the Modules screen use ModuleToggler), modules:sync (installed disabled,
 * disabled when found enabled, security log), the Modules screen's row and `security:check`
 * (`installation.devtools`). Packages: PackagesTest.
 *
 * @internal
 */
final class DevelopmentOnlyTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use CleanDatabaseTrait;
    use ModuleFixturesTrait;

    protected $migrate   = false;
    protected $refresh   = false;
    protected $namespace = null;

    private const DEV_ONLY = "public bool \$enabledByDefault = false;\n    public bool \$developmentOnly = true;";

    protected function setUp(): void
    {
        parent::setUp();
        $this->dropAllTables();
        service('language')->setLocale('en');
    }

    protected function tearDown(): void
    {
        $this->removeTempRoot();
        parent::tearDown();
    }

    public function testTheManifestKeyIsABooleanAndNeverEnabledByDefault(): void
    {
        $validator = new ManifestValidator();
        $path      = ROOTPATH . 'modules/Devtools';
        $base      = ['name' => 'Devtools', 'version' => '0.1.0'];

        $this->assertSame([], $validator->validateProperties($base + ['developmentOnly' => true, 'enabledByDefault' => false], $path));
        $this->assertSame([], $validator->validateProperties($base + ['developmentOnly' => false, 'enabledByDefault' => true], $path));
        $this->assertSame([], $validator->validateProperties($base, $path));

        $errors = $validator->validateProperties($base + ['developmentOnly' => 'yes', 'enabledByDefault' => false], $path);
        $this->assertSame([lang('Core.manifest.error', ['Devtools', '$developmentOnly', lang('Core.manifest.mustBe', ['bool'])])], $errors);

        $this->assertNotSame([], $validator->validateProperties($base + ['developmentOnly' => 1, 'enabledByDefault' => false], $path));

        $errors = $validator->validateProperties($base + ['developmentOnly' => true, 'enabledByDefault' => true], $path);
        $this->assertSame([lang('Core.manifest.error', ['Devtools', '$enabledByDefault', lang('Core.manifest.developmentOnlyEnabled')])], $errors);
    }

    public function testTheRule(): void
    {
        $manifest = (new ModuleDiscovery())->discover()['Devtools']->manifest;
        $this->assertNotNull($manifest);

        $this->assertTrue((new DevelopmentOnly('production'))->refuses($manifest));
        $this->assertTrue((new DevelopmentOnly('testing'))->refuses($manifest));
        $this->assertFalse((new DevelopmentOnly('development'))->refuses($manifest));
        $this->assertFalse((new DevelopmentOnly('production'))->refuses((new ModuleDiscovery())->discover()['News']->manifest ?? $manifest));
    }

    public function testEnablingIsRefusedOutsideDevelopment(): void
    {
        $name = $this->fixtureModule(self::DEV_ONLY);
        $this->synchronizer('development')->sync();
        $this->assertFalse($this->enabled($name), 'Installed disabled (enabledByDefault = false).');

        try {
            $this->toggler('production')->enable($name);
            $this->fail('A development-only module was enabled in production.');
        } catch (ModuleToggleException $e) {
            $this->assertSame(lang('Core.toggle.developmentOnly', [$name]), $e->getMessage());
        }

        $this->assertFalse($this->enabled($name));

        $this->assertSame([$name], $this->toggler('development')->enable($name));
        $this->assertTrue($this->enabled($name));
    }

    public function testEnablingADependantPullsTheRuleIn(): void
    {
        $tool = $this->fixtureModule(self::DEV_ONLY);
        $user = $this->fixtureModule('', [$tool => '^0.1']);
        $this->synchronizer('development')->sync();
        $this->setEnabled($user, false);

        $this->expectException(ModuleToggleException::class);
        $this->expectExceptionMessage(lang('Core.toggle.developmentOnly', [$tool]));
        $this->toggler('production')->enable($user, true);
    }

    public function testASyncOutsideDevelopmentDisablesItAndLogsIt(): void
    {
        $name = $this->fixtureModule(self::DEV_ONLY);
        $this->synchronizer('development')->sync();
        $this->toggler('development')->enable($name);

        $report = $this->synchronizer('production')->sync();

        $this->assertSame([$name], $report->devOnlyDisabled);
        $this->assertContains(lang('Core.sync.devOnlyDisabled', [$name]), $report->lines());
        $this->assertFalse($this->enabled($name));

        $entry = db_connect()->table('activity_log')->where('action', 'devonly_disabled')->get()->getRowArray();
        $this->assertIsArray($entry);
        $this->assertSame('security', $entry['type']);
        $this->assertSame($name, $entry['summary']);

        // Idempotent, and a development sync leaves it alone.
        $this->assertSame([], $this->synchronizer('production')->sync()->devOnlyDisabled);
        $this->toggler('development')->enable($name);
        $this->assertSame([], $this->synchronizer('development')->sync()->devOnlyDisabled);
        $this->assertTrue($this->enabled($name));
    }

    public function testFirstSyncOutsideDevelopmentInstallsItDisabled(): void
    {
        $this->synchronizer('production', true)->sync();

        $this->assertFalse($this->enabled('Devtools'));
        $this->assertTrue($this->enabled('News'));
    }

    public function testTheModulesScreenMarksItAndOffersNoEnableButton(): void
    {
        $this->synchronizer('development')->sync();
        $rows = (new ModuleOverview($this->discovery()))->rows();

        $this->assertTrue($rows['Devtools']['developmentOnly']);
        $this->assertTrue($rows['Devtools']['blocked'], 'The suite does not run in development.');
        $this->assertFalse($rows['News']['developmentOnly']);
        $this->assertFalse($rows['News']['blocked']);
    }

    public function testSecurityCheckReportsItOutsideDevelopment(): void
    {
        $this->assertSame(CheckResult::ERROR, $this->devtoolsResult('production', ['Devtools'])->status);
        $this->assertStringContainsString('Devtools', $this->devtoolsResult('production', ['Devtools'])->message);
        $this->assertSame(CheckResult::OK, $this->devtoolsResult('production', [])->status);
        $this->assertSame(CheckResult::INFO, $this->devtoolsResult('development', ['Devtools'])->status);
        $this->assertSame(CheckResult::INFO, $this->devtoolsResult('development', [])->status);
    }

    public function testSecurityCheckReadsTheRegistry(): void
    {
        $this->synchronizer('development')->sync();
        $this->toggler('development')->enable('Devtools');
        $check = new InstallationCheck(developmentOnly: new DevelopmentOnly('production'));
        $report = new SecurityReport();
        $check->run($report);

        $result = $this->find($report, 'installation.devtools');
        $this->assertSame(CheckResult::ERROR, $result->status);
        $this->assertStringContainsString('Devtools', $result->message);
    }

    // ---------------------------------------------------------------- helpers

    /**
     * @param list<string> $enabled
     */
    private function devtoolsResult(string $environment, array $enabled): CheckResult
    {
        $report = new SecurityReport();
        (new InstallationCheck(devOnlyModules: static fn (): array => $enabled, developmentOnly: new DevelopmentOnly($environment)))->run($report);

        return $this->find($report, 'installation.devtools');
    }

    private function find(SecurityReport $report, string $id): CheckResult
    {
        foreach ($report->results() as $result) {
            if ($result->id === $id) {
                return $result;
            }
        }

        $this->fail("No {$id} result.");
    }

    private function toggler(string $environment): ModuleToggler
    {
        return new ModuleToggler($this->discovery(), developmentOnly: new DevelopmentOnly($environment));
    }

    private function synchronizer(string $environment, ?bool $autoEnable = null): ModuleSynchronizer
    {
        $public = WRITEPATH . 'tests/public-' . md5($this->tempRoot());

        if (! is_dir($public)) {
            mkdir($public, 0777, true);
        }

        return new ModuleSynchronizer(
            $this->discovery(),
            new NamespaceFile($this->tempRoot() . '/ModuleNamespaces.php'),
            assets: new AssetPublisher($public),
            autoEnable: $autoEnable,
            developmentOnly: new DevelopmentOnly($environment),
        );
    }

    private function enabled(string $name): bool
    {
        return (bool) (model(ModuleModel::class, false)->allByName()[$name]['enabled'] ?? false);
    }

    private function setEnabled(string $name, bool $enabled): void
    {
        db_connect()->table('modules')->where('name', $name)->update(['enabled' => $enabled ? 1 : 0]);
    }
}
