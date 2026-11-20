<?php

declare(strict_types=1);

namespace Modules\Core\Tests;

use CodeIgniter\Events\Events;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Modules\Core\Config\Packages;
use Modules\Core\Maintenance\MaintenanceMode;
use Modules\Core\Models\ModuleModel;
use Modules\Core\Modules\DevelopmentOnly;
use Modules\Core\Modules\ModuleSynchronizer;
use Modules\Core\Modules\SyncReport;
use Modules\Core\Notifications\Types\PackageProcessed;
use Modules\Core\Packages\ManifestReader;
use Modules\Core\Packages\ModuleUninstalling;
use Modules\Core\Packages\PackageBackup;
use Modules\Core\Packages\PackageInfo;
use Modules\Core\Packages\PackageInstaller;
use Modules\Core\Packages\PackageRefused;
use Modules\Core\Packages\PackageValidator;
use Modules\Core\Packages\Safeguards;
use Modules\Core\Packages\StagedPackages;
use Modules\Core\Packages\Uninstaller;
use ZipArchive;

/**
 * Module and theme packages (acceptance criterion 3, docs/installing-modules.md): validation (zip-slip,
 * links, manifest, extensions, zip bombs, `php -l`, signatures), install, upgrade, rollback,
 * uninstall (with and without data), staged operations and the auto-enable rule of modules:sync.
 *
 * @internal
 */
final class PackagesTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use CleanDatabaseTrait;
    use ModuleFixturesTrait;

    protected $migrate   = false;
    protected $refresh   = false;
    protected $namespace = null;

    private string $work = '';

    /** @var list<PackageBackup> */
    private array $backups = [];

    private MaintenanceMode $maintenance;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dropAllTables();
        service('language')->setLocale('en');
        $this->work        = sys_get_temp_dir() . '/kps-packages-' . bin2hex(random_bytes(6));
        $this->maintenance = new MaintenanceMode($this->work . '-maintenance.json');
        mkdir($this->work, 0777, true);
        $this->backups = [];
        $this->synchronizer()->sync();
    }

    protected function tearDown(): void
    {
        PackageValidator::removeFolder($this->work);
        @unlink($this->work . '-maintenance.json');
        $this->removeTempRoot();
        Events::removeAllListeners(ModuleUninstalling::EVENT);
        parent::tearDown();
    }

    // ---------------------------------------------------------------- validation

    public function testTheManifestIsReadWithoutRunningIt(): void
    {
        $code = <<<'PHP'
            <?php
            namespace Modules\Demo\Config;
            file_put_contents('/tmp/kps-should-not-exist', 'ran');
            final class Module extends \Modules\Core\Config\ModuleManifest
            {
                public string $name = 'Demo';
                public string $version = '1.2.0';
                public string $icon = PHP_EOL;
                public array $requires = ['Core' => '^1.0', 'News' => '>=0.1'];
                public array $widgets = [\Modules\Demo\Widgets\Latest::class];
                public function __construct() { $this->name = 'Other'; }
            }
            PHP;

        $read = (new ManifestReader())->read($code);

        $this->assertFileDoesNotExist('/tmp/kps-should-not-exist');
        $this->assertSame('Module', $read['class']);
        $this->assertSame('Modules\Demo\Config', $read['namespace']);
        $this->assertSame('Demo', $read['properties']['name']);
        $this->assertSame('1.2.0', $read['properties']['version']);
        $this->assertSame(ManifestReader::UNKNOWN, $read['properties']['icon'], 'A constant is not evaluated.');
        $this->assertSame(['Core' => '^1.0', 'News' => '>=0.1'], $read['properties']['requires']);
        $this->assertSame(['Modules\Demo\Widgets\Latest'], $read['properties']['widgets']);
        $this->assertArrayNotHasKey('this', $read['properties']);
    }

    public function testAValidModulePackageIsDescribed(): void
    {
        $name = $this->uniqueModuleName();
        $info = $this->validator()->validate($this->modulePackage($name, '1.0.0', ['Assets/js/app.js' => 'export {};', 'README' => 'Hi']));

        $this->assertSame(PackageInfo::MODULE, $info->type);
        $this->assertSame($name, $info->name);
        $this->assertSame('1.0.0', $info->version);
        $this->assertSame('ACME', $info->author);
        $this->assertSame(['Core' => '^1.0'], $info->requires);
        $this->assertContains(strtolower($name) . '-items.list', $info->permissions);
        $this->assertSame(['Items'], $info->menus);
        $this->assertArrayHasKey('Config/Module.php', $info->files);
        $this->assertSame(64, strlen($info->sha256));
        $this->assertFalse($info->signed);
    }

    public function testRefusedPackagesAreRefusedAndLogged(): void
    {
        $name = $this->uniqueModuleName();
        $good = $this->moduleFiles($name, '1.0.0');

        $cases = [
            'zip-slip'           => [[$name . '/../evil.php' => '<?php'] + $this->prefixed($name, $good), null],
            'two folders'        => [$this->prefixed($name, $good) + ['Other/x.txt' => 'x'], lang('Core.packages.singleFolder')],
            'no manifest'        => [[$name . '/README.md' => 'x'], lang('Core.packages.noManifest')],
            'name mismatch'      => [$this->prefixed($name, ['Config/Module.php' => $this->manifest('Wrong', '1.0.0')]), lang('Core.packages.manifestClass', ['Modules\\' . $name . '\\Config\\Module'])],
            'no version'         => [$this->prefixed($name, ['Config/Module.php' => $this->manifest($name, 'latest')]), lang('Core.packages.version')],
            'bad extension'      => [$this->prefixed($name, $good + ['Views/x.phtml' => '<?php echo 1;']), lang('Core.packages.extension', ['Views/x.phtml'])],
            'htaccess'           => [$this->prefixed($name, $good + ['.htaccess' => 'Allow from all']), lang('Core.packages.extension', ['.htaccess'])],
            'reserved name'      => [$this->prefixed('Security', ['Config/Module.php' => $this->manifest('Security', '1.0.0')]), lang('Core.packages.reserved', ['Security'])],
            'core module'        => [$this->prefixed('Users', ['Config/Module.php' => $this->manifest('Users', '9.0.0')]), lang('Core.packages.coreModule', ['Users'])],
        ];

        foreach ($cases as $case => [$entries, $message]) {
            $zip = $this->zip($entries);

            try {
                $this->validator()->validate($zip, null, null, $case);
                $this->fail("The package with {$case} was accepted.");
            } catch (PackageRefused $e) {
                if ($message !== null) {
                    $this->assertSame($message, $e->getMessage(), $case);
                }
            }
        }

        $this->assertSame(count($cases), db_connect()->table('activity_log')->where('action', 'package_refused')->where('type', 'security')->countAllResults());
    }

    public function testSymbolicLinksAndZipBombsAreRefused(): void
    {
        $name = $this->uniqueModuleName();
        $link = $this->zip($this->prefixed($name, $this->moduleFiles($name, '1.0.0') + ['link' => '/etc/passwd']), [$name . '/link']);
        $this->assertRefused($link, lang('Core.archive.link', [$name . '/link']));

        $bomb = $this->zip($this->prefixed($name, $this->moduleFiles($name, '1.0.0') + ['big.txt' => str_repeat('0', 4 * 1024 * 1024)]));
        $this->assertRefused($bomb, lang('Core.archive.ratio', [$name . '/big.txt']));

        $config           = new Packages();
        $config->maxFiles = 2;
        $many             = $this->zip($this->prefixed($name, $this->moduleFiles($name, '1.0.0') + ['a.txt' => 'a', 'b.txt' => 'b']));
        $this->expectException(PackageRefused::class);
        (new PackageValidator($config))->validate($many);
    }

    public function testPhpFilesAreSyntaxCheckedBeforeInstalling(): void
    {
        $name = $this->uniqueModuleName();
        $zip  = $this->modulePackage($name, '1.0.0', ['Controllers/Broken.php' => "<?php\nfunction (\n"]);
        $info = $this->validator()->validate($zip);

        try {
            $this->validator()->extract($zip, $info, $this->work . '/x');
            $this->fail('A PHP syntax error was accepted.');
        } catch (PackageRefused $e) {
            $this->assertStringContainsString('Controllers/Broken.php', $e->getMessage());
        }

        $this->assertDirectoryDoesNotExist($this->work . '/x/' . $name, 'Nothing is left behind.');
    }

    public function testSignatures(): void
    {
        $name    = $this->uniqueModuleName();
        $zip     = $this->modulePackage($name, '1.0.0');
        $keys    = sodium_crypto_sign_keypair();
        $keyId   = random_bytes(8);
        $public  = base64_encode('Ed' . $keyId . sodium_crypto_sign_publickey($keys));
        $config  = new Packages();
        $config->publicKeys = ["untrusted comment: minisign public key\n" . $public];
        $signed  = self::sign((string) file_get_contents($zip), sodium_crypto_sign_secretkey($keys), $keyId);

        $info = (new PackageValidator($config))->validate($zip, $signed);
        $this->assertTrue($info->signed);

        $other = sodium_crypto_sign_keypair();
        $this->assertRefused($zip, lang('Core.packages.badSignature'), self::sign((string) file_get_contents($zip), sodium_crypto_sign_secretkey($other), $keyId), $config);
        $this->assertRefused($zip, lang('Core.packages.unknownKey', [strtoupper(bin2hex(strrev('12345678')))]), self::sign((string) file_get_contents($zip), sodium_crypto_sign_secretkey($keys), '12345678'), $config);

        $config->requireSignature = true;
        $this->assertRefused($zip, lang('Core.packages.signatureRequired'), null, $config);
    }

    public function testAThemePackageIsValidated(): void
    {
        $zip  = $this->zip([
            'kps-test-theme/Theme.php'          => "<?php\nnamespace Themes\\KpsTestTheme;\nfinal class Theme extends \\Modules\\Core\\Theme\\ThemeManifest\n{\n    public string \$name = 'Test theme';\n    public string \$version = '1.0.0';\n    public ?string \$parent = 'default';\n}\n",
            'kps-test-theme/Assets/css/a.css'   => 'body{}',
        ]);
        $info = $this->validator()->validate($zip, null, PackageInfo::THEME);

        $this->assertSame(PackageInfo::THEME, $info->type);
        $this->assertSame('kps-test-theme', $info->name);
        $this->assertSame('default', $info->parent);
        $this->assertRefused($zip, lang('Core.packages.notModule'), null, null, PackageInfo::MODULE);
    }

    // ---------------------------------------------------------------- install

    public function testInstallUpgradeAndDowngrade(): void
    {
        $name      = $this->uniqueModuleName();
        $installer = $this->installer();
        $zip       = $this->modulePackage($name, '1.0.0', ['Database/Migrations/2026-01-01-000001_CreateItems.php' => $this->migration($name)]);
        $info      = $this->validator()->validate($zip);

        $during = null;
        $result = $this->installer(function () use (&$during): SyncReport {
            $during ??= $this->maintenance->isOn();

            return $this->synchronizer()->sync();
        })->install($zip, $info, ['enable' => true]);

        $this->assertSame('install', $result['action']);
        $this->assertTrue($during, 'Maintenance mode is on while the code changes…');
        $this->assertFalse($this->maintenance->isOn(), '…and off afterwards.');
        $this->assertCount(1, $this->backups, 'A backup is made first.');
        $this->assertFileExists($this->tempRoot() . '/' . $name . '/Config/Module.php');
        $this->assertSame([], glob($this->tempRoot() . '/.' . $name . '*') ?: [], 'No hidden folder is left.');
        $row = model(ModuleModel::class, false)->allByName()[$name];
        $this->assertTrue($row['enabled']);
        $this->assertSame('1.0.0', $row['version']);
        $this->assertTrue(db_connect()->tableExists(strtolower($name) . '_items'));
        $this->assertSame(1, db_connect()->table('activity_log')->where('action', 'package_installed')->countAllResults());

        // Same version and downgrade need --force; the upgrade keeps the state.
        $this->assertSame([lang('Core.packages.sameVersion', [$name, '1.0.0'])], $installer->plan($info)['problems']);
        $old = $this->validator()->validate($this->modulePackage($name, '0.9.0'));
        $this->assertSame([lang('Core.packages.downgrade', [$name, '1.0.0', '0.9.0'])], $installer->plan($old)['problems']);
        $this->assertSame([], $installer->plan($old, true)['problems']);

        // An upgrade replaces the folder; this process keeps the 1.0.0 classes it loaded, which is
        // why the console runs modules:sync in a new process (SparkSync).
        db_connect()->table('modules')->where('name', $name)->update(['enabled' => 0]);
        $new = $this->modulePackage($name, '1.1.0', ['Database/Migrations/2026-01-01-000001_CreateItems.php' => $this->migration($name)]);
        $this->assertSame('upgrade', $installer->install($new, $this->validator()->validate($new))['action']);
        $this->assertStringContainsString("'1.1.0'", (string) file_get_contents($this->tempRoot() . '/' . $name . '/Config/Module.php'));
        $this->assertFalse(model(ModuleModel::class, false)->allByName()[$name]['enabled'], 'An upgrade keeps the module disabled.');
    }

    public function testAnotherAuthorAndMissingDependenciesAreRefused(): void
    {
        $name = $this->uniqueModuleName();
        $this->installer()->install($zip = $this->modulePackage($name, '1.0.0'), $this->validator()->validate($zip));

        $other = $this->validator()->validate($this->modulePackage($name, '2.0.0', [], 'Someone else'));
        $this->assertSame([lang('Core.packages.otherAuthor', [$name, 'ACME', 'Someone else'])], $this->installer()->plan($other)['problems']);

        $needs = $this->validator()->validate($this->modulePackage($this->uniqueModuleName(), '1.0.0', [], 'ACME', ['Core' => '^1.0', 'Nowhere' => '^1.0']));
        $this->assertStringContainsString('Nowhere', implode(' ', $this->installer()->plan($needs)['problems']));

        $core = $this->validator()->validate($this->modulePackage($this->uniqueModuleName(), '1.0.0', [], 'ACME', ['Core' => '^9.0']));
        $this->assertStringContainsString('^9.0', implode(' ', $this->installer()->plan($core)['problems']));
    }

    public function testAFailedInstallRestoresThePreviousVersion(): void
    {
        $name = $this->uniqueModuleName();
        $this->installer()->install($zip = $this->modulePackage($name, '1.0.0'), $this->validator()->validate($zip));

        // modules:sync fails on the new version (e.g. its full manifest validation).
        $bad      = $this->modulePackage($name, '1.1.0');
        $info     = $this->validator()->validate($bad);
        $failing  = static function (): SyncReport {
            throw new \Modules\Core\Modules\SyncFailedException(['$menu: invalid zone']);
        };

        try {
            $this->installer($failing)->install($bad, $info);
            $this->fail('The broken upgrade was installed.');
        } catch (PackageRefused $e) {
            $this->assertStringContainsString($name . ' 1.1.0', $e->getMessage());
            $this->assertStringContainsString('$menu: invalid zone', $e->getMessage());
        }

        $this->assertStringContainsString("'1.0.0'", (string) file_get_contents($this->tempRoot() . '/' . $name . '/Config/Module.php'), 'The previous folder is back.');
        $this->assertSame('1.0.0', model(ModuleModel::class, false)->allByName()[$name]['version']);
        $this->assertFalse($this->maintenance->isOn());
        $this->assertSame(1, db_connect()->table('activity_log')->where('action', 'package_failed')->countAllResults());
    }

    public function testWithoutABackupNothingIsInstalledUnlessAsked(): void
    {
        $name      = $this->uniqueModuleName();
        $zip       = $this->modulePackage($name, '1.0.0');
        $installer = new PackageInstaller($this->validator(), new Safeguards($this->maintenance, static function (PackageBackup $request): void {
        }), fn (): SyncReport => $this->synchronizer()->sync(), null, $this->tempRoot(), $this->work . '/themes', $this->work);

        try {
            $installer->install($zip, $this->validator()->validate($zip));
            $this->fail('Installed without a backup.');
        } catch (PackageRefused $e) {
            $this->assertSame(lang('Core.packages.noBackup'), $e->getMessage());
        }

        $this->assertDirectoryDoesNotExist($this->tempRoot() . '/' . $name);
        $installer->install($zip, $this->validator()->validate($zip), ['backup' => false]);
        $this->assertDirectoryExists($this->tempRoot() . '/' . $name);
    }

    public function testNewModulesStayDisabledInProductionUnlessAutoEnabled(): void
    {
        $name = $this->fixtureModule();

        (new ModuleSynchronizer(
            $this->discovery(),
            new \Modules\Core\Modules\NamespaceFile($this->tempRoot() . '/ModuleNamespaces.php'),
            assets: new \Modules\Core\Frontend\AssetPublisher(WRITEPATH . 'tests/public-' . md5($this->tempRoot())),
            autoEnable: false,
        ))->sync();

        $this->assertFalse(model(ModuleModel::class, false)->allByName()[$name]['enabled'], 'Found by a later sync: installed, disabled.');

        $this->dropAllTables();
        (new ModuleSynchronizer(
            $this->discovery(),
            new \Modules\Core\Modules\NamespaceFile($this->tempRoot() . '/ModuleNamespaces.php'),
            assets: new \Modules\Core\Frontend\AssetPublisher(WRITEPATH . 'tests/public-' . md5($this->tempRoot())),
            autoEnable: false,
        ))->sync();
        $this->assertTrue(model(ModuleModel::class, false)->allByName()[$name]['enabled'], 'The first sync of an installation enables every module.');
    }

    public function testAModuleDisabledByDefaultIsInstalledDisabledEvenOnTheFirstSync(): void
    {
        $name = $this->fixtureModule('public bool $enabledByDefault = false;');
        $sync = fn (): \Modules\Core\Modules\SyncReport => (new ModuleSynchronizer(
            $this->discovery(),
            new \Modules\Core\Modules\NamespaceFile($this->tempRoot() . '/ModuleNamespaces.php'),
            assets: new \Modules\Core\Frontend\AssetPublisher(WRITEPATH . 'tests/public-' . md5($this->tempRoot())),
            autoEnable: true,
        ))->sync();

        $sync();
        $modules = model(ModuleModel::class, false)->allByName();
        $this->assertFalse($modules[$name]['enabled'], 'D-32-01: installed, disabled.');
        $this->assertTrue($modules['Core']['enabled']);

        // Enabled by hand, a later sync keeps it enabled.
        model(ModuleModel::class, false)->update($modules[$name]['id'], ['enabled' => 1]);
        $sync();
        $this->assertTrue(model(ModuleModel::class, false)->allByName()[$name]['enabled']);
    }

    // ---------------------------------------------------------------- uninstall

    public function testUninstallRemovesTheModuleAndItsData(): void
    {
        $name   = $this->uniqueModuleName();
        $lower  = strtolower($name);
        $zip    = $this->modulePackage($name, '1.0.0', ['Database/Migrations/2026-01-01-000001_CreateItems.php' => $this->migration($name)]);
        $this->installer()->install($zip, $this->validator()->validate($zip), ['enable' => true]);
        db_connect()->table('group_permissions')->insert(['group' => 'editor', 'permission' => $lower . '-items.*']);
        db_connect()->table('settings')->insert(['class' => $name, 'key' => 'perPage', 'value' => '5', 'type' => 'integer', 'context' => null, 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')]);

        $seen = null;
        Events::on(ModuleUninstalling::EVENT, static function (ModuleUninstalling $event) use (&$seen): void {
            $seen = $event;
            $event->note('listener ran');
        });

        $calendar = new class () implements \Modules\Core\Calendar\CalendarPublisher {
            /** @var list<string> */
            public array $purged = [];

            public function publish(string $module, string $key, \Modules\Core\Calendar\CalendarEvent $event): bool
            {
                return false;
            }

            public function unpublish(string $module, string $key): bool
            {
                return false;
            }

            public function purgeModule(string $module): int
            {
                $this->purged[] = $module;

                return 3;
            }
        };
        \Config\Services::injectMock('calendar', $calendar);

        $notes = $this->uninstaller()->uninstallModule($name);

        $this->assertSame([$name], $calendar->purged, 'Its calendar entries are deleted (Core calendar contract).');
        \Config\Services::resetSingle('calendar');

        $this->assertInstanceOf(ModuleUninstalling::class, $seen);
        $this->assertSame($name, $seen->module);
        $this->assertContains($lower . '-items.list', $seen->permissions);
        $this->assertSame([lang('Core.packages.calendarPurged', [3]), 'listener ran'], $notes);
        $this->assertDirectoryDoesNotExist($this->tempRoot() . '/' . $name);
        $this->assertFalse(db_connect()->tableExists($lower . '_items'), 'Its migrations are rolled back.');
        $this->assertSame(0, db_connect()->table('permissions')->where('module', $name)->countAllResults());
        $this->assertSame(0, db_connect()->table('group_permissions')->like('permission', $lower . '-', 'after')->countAllResults());
        $this->assertSame(0, db_connect()->table('settings')->where('class', $name)->countAllResults());
        $this->assertArrayNotHasKey($name, model(ModuleModel::class, false)->allByName());
        $this->assertSame(0, db_connect()->table('module_declarations')->where('module', $name)->countAllResults());
        $this->assertCount(2, $this->backups);
        $this->assertSame(1, db_connect()->table('activity_log')->where('action', 'module_uninstalled')->countAllResults());
    }

    public function testUninstallKeepingTheData(): void
    {
        $name = $this->uniqueModuleName();
        $zip  = $this->modulePackage($name, '1.0.0', ['Database/Migrations/2026-01-01-000001_CreateItems.php' => $this->migration($name)]);
        $this->installer()->install($zip, $this->validator()->validate($zip), ['enable' => true]);

        $this->uninstaller()->uninstallModule($name, ['keepData' => true]);

        $this->assertDirectoryDoesNotExist($this->tempRoot() . '/' . $name);
        $this->assertTrue(db_connect()->tableExists(strtolower($name) . '_items'), 'The tables stay.');
    }

    public function testCoreModulesAndRequiredModulesCannotBeUninstalled(): void
    {
        $this->assertSame([lang('Core.packages.uninstallCore', ['Users'])], $this->uninstaller()->moduleProblems('Users'));

        $base = $this->fixtureModule();
        $this->fixtureModule('', [$base => '^0.1']);
        $this->synchronizer()->sync();

        $problems = $this->uninstaller()->moduleProblems($base);
        $this->assertCount(1, $problems);
        $this->assertStringStartsWith($base . ' cannot be uninstalled', $problems[0]);
    }

    // ---------------------------------------------------------------- staged operations

    public function testStagedInstallAndUninstall(): void
    {
        $name   = $this->uniqueModuleName();
        $staged = new StagedPackages($this->validator(), $this->work . '/staged');
        $userId = $this->superadmin();

        $row = $staged->upload($this->modulePackage($name, '1.0.0'), 'package.zip', null, $userId);
        $this->assertSame('uploaded', $row['status']);
        $this->assertFileExists($this->work . '/staged/' . $row['file']);
        $this->assertSame([], $staged->pending(), 'Nothing runs before the approval.');

        $staged->approve((int) $row['id'], $userId);
        $this->assertCount(1, $staged->pending());

        $lines  = [];
        $result = $staged->process(static function (string $line) use (&$lines): void {
            $lines[] = $line;
        }, [], $this->installer(), $this->uninstaller());

        $this->assertSame(['done' => 1, 'failed' => 0], $result);
        $done = $staged->find((int) $row['id']);
        $this->assertSame('installed', $done['status']);
        $this->assertStringContainsString(lang('Core.packages.step.done', [$name . ' 1.0.0']), (string) $done['log']);
        $this->assertFileDoesNotExist($this->work . '/staged/' . $row['file']);
        $this->assertDirectoryExists($this->tempRoot() . '/' . $name);
        $this->assertSame(1, db_connect()->table('notifications')->where('user_id', $userId)->where('type', PackageProcessed::class)->countAllResults());

        // Uninstall request → console.
        $id = $staged->requestUninstall(PackageInfo::MODULE, $name, false, $userId, $this->uninstaller());
        $this->expectExceptionMessage(lang('Core.packages.alreadyPending', [$name]));

        try {
            $staged->requestUninstall(PackageInfo::MODULE, $name, false, $userId, $this->uninstaller());
        } finally {
            $staged->process(static function (): void {
            }, [], $this->installer(), $this->uninstaller());
            $this->assertSame('installed', $staged->find($id)['status']);
            $this->assertDirectoryDoesNotExist($this->tempRoot() . '/' . $name);
        }
    }

    public function testDiscardExpiryAndTamperedFiles(): void
    {
        $staged = new StagedPackages($this->validator(), $this->work . '/staged');
        $userId = $this->superadmin();

        $one = $staged->upload($this->modulePackage($this->uniqueModuleName(), '1.0.0'), 'a.zip', null, $userId);
        $staged->discard((int) $one['id']);
        $this->assertSame('discarded', $staged->find((int) $one['id'])['status']);
        $this->assertFileDoesNotExist($this->work . '/staged/' . $one['file']);

        $two = $staged->upload($this->modulePackage($this->uniqueModuleName(), '1.0.0'), 'b.zip', null, $userId);
        file_put_contents($this->work . '/staged/' . $two['file'], 'changed', FILE_APPEND);

        try {
            $staged->approve((int) $two['id'], $userId);
            $this->fail('A changed file was approved.');
        } catch (PackageRefused $e) {
            $this->assertSame(lang('Core.packages.fileChanged'), $e->getMessage());
        }

        $three = $staged->upload($this->modulePackage($this->uniqueModuleName(), '1.0.0'), 'c.zip', null, $userId);
        db_connect()->table('module_packages')->where('id', $three['id'])->update(['expires_at' => '2000-01-01 00:00:00']);
        $this->assertSame(1, $staged->expire());
        $this->assertSame('expired', $staged->find((int) $three['id'])['status']);
        $this->assertFileDoesNotExist($this->work . '/staged/' . $three['file']);
    }

    public function testADevelopmentOnlyPackageIsRefusedOutsideDevelopment(): void
    {
        $name = $this->uniqueModuleName();
        $zip  = $this->modulePackage($name, '1.0.0', body: "public bool \$enabledByDefault = false;\n    public bool \$developmentOnly = true;");

        try {
            (new PackageValidator(null, new \Modules\Core\Packages\ManifestReader(), null, new DevelopmentOnly('production')))->validate($zip);
            $this->fail('A development-only package was accepted in production.');
        } catch (PackageRefused $e) {
            $this->assertSame(lang('Core.packages.developmentOnly', [$name]), $e->getMessage());
        }

        $info = (new PackageValidator(null, new \Modules\Core\Packages\ManifestReader(), null, new DevelopmentOnly('development')))->validate($zip);
        $this->assertSame($name, $info->name);
    }

    public function testAPackageWithANonBooleanDevelopmentOnlyIsRefused(): void
    {
        $zip = $this->modulePackage($this->uniqueModuleName(), '1.0.0', body: "public \$developmentOnly = 'yes';");

        $this->expectException(PackageRefused::class);
        $this->expectExceptionMessage(lang('Core.packages.developmentOnlyInvalid'));
        (new PackageValidator(null, new \Modules\Core\Packages\ManifestReader(), null, new DevelopmentOnly('development')))->validate($zip);
    }

    // ---------------------------------------------------------------- helpers

    private function validator(?Packages $config = null): PackageValidator
    {
        return new PackageValidator($config);
    }

    /**
     * @param (\Closure(): SyncReport)|null $sync
     */
    private function installer(?\Closure $sync = null): PackageInstaller
    {
        return new PackageInstaller(
            $this->validator(),
            $this->safeguards(),
            $sync ?? fn (): SyncReport => $this->synchronizer()->sync(),
            new \Modules\Core\Modules\ModuleToggler($this->discovery()),
            $this->tempRoot(),
            $this->work . '/themes',
            $this->work,
        );
    }

    private function uninstaller(): Uninstaller
    {
        return new Uninstaller($this->safeguards(), fn (): SyncReport => $this->synchronizer()->sync(), modulesRoot: $this->tempRoot(), themesRoot: $this->work . '/themes');
    }

    private function safeguards(): Safeguards
    {
        return new Safeguards($this->maintenance, function (PackageBackup $request): void {
            $this->backups[] = $request;
            $request->done('test-' . count($this->backups) . '.kpbackup');
        });
    }

    private function superadmin(): int
    {
        $users = model(\CodeIgniter\Shield\Models\UserModel::class);
        $user  = new \CodeIgniter\Shield\Entities\User(['username' => 'root' . bin2hex(random_bytes(3)), 'email' => bin2hex(random_bytes(4)) . '@example.com', 'password' => 'Correct-Horse-Battery-42']);
        $users->save($user);

        return (int) $users->getInsertID();
    }

    private function assertRefused(string $zip, ?string $message, ?string $signature = null, ?Packages $config = null, ?string $type = null): void
    {
        try {
            $this->validator($config)->validate($zip, $signature, $type);
            $this->fail('The package was accepted.');
        } catch (PackageRefused $e) {
            if ($message !== null) {
                $this->assertSame($message, $e->getMessage());
            }
        }
    }

    /**
     * @param array<string, string> $requires
     */
    private function manifest(string $name, string $version, string $author = 'ACME', array $requires = ['Core' => '^1.0'], string $body = ''): string
    {
        $requires = var_export($requires, true);
        $lower    = strtolower($name);

        return <<<PHP
            <?php

            declare(strict_types=1);

            namespace Modules\\{$name}\\Config;

            final class Module extends \\Modules\\Core\\Config\\ModuleManifest
            {
                public string \$name    = '{$name}';
                public string \$version = '{$version}';
                public string \$author  = '{$author}';
                public array \$requires = {$requires};
                public array \$resources = ['items' => ['label' => 'Items', 'type' => 'crud', 'except' => ['import']]];
                public array \$defaultGrants = ['admin' => ['{$lower}-items.list']];
                {$body}
            }
            PHP;
    }

    /**
     * @param array<string, string> $requires
     *
     * @return array<string, string>
     */
    private function moduleFiles(string $name, string $version, string $author = 'ACME', array $requires = ['Core' => '^1.0'], string $body = ''): array
    {
        $body = $body !== '' ? $body : "public array \$menu = [['resource' => 'items', 'label' => 'Items']];";

        return [
            'Config/Module.php' => $this->manifest($name, $version, $author, $requires, $body),
            'Config/Routes.php' => "<?php\n",
        ];
    }

    /**
     * @param array<string, string> $extra
     * @param array<string, string> $requires
     */
    private function modulePackage(string $name, string $version, array $extra = [], string $author = 'ACME', array $requires = ['Core' => '^1.0'], string $body = ''): string
    {
        return $this->zip($this->prefixed($name, $this->moduleFiles($name, $version, $author, $requires, $body) + $extra));
    }

    private function migration(string $name): string
    {
        $table = strtolower($name) . '_items';

        return <<<PHP
            <?php

            namespace Modules\\{$name}\\Database\\Migrations;

            use CodeIgniter\\Database\\Migration;

            final class CreateItems extends Migration
            {
                public function up(): void
                {
                    \$this->forge->addField(['id' => ['type' => 'INT', 'auto_increment' => true]]);
                    \$this->forge->addPrimaryKey('id');
                    \$this->forge->createTable('{$table}');
                }

                public function down(): void
                {
                    \$this->forge->dropTable('{$table}', true);
                }
            }
            PHP;
    }

    /**
     * @param array<string, string> $files
     *
     * @return array<string, string>
     */
    private function prefixed(string $folder, array $files): array
    {
        $result = [];

        foreach ($files as $path => $content) {
            $result[$folder . '/' . $path] = $content;
        }

        return $result;
    }

    /**
     * @param array<string, string> $entries
     * @param list<string>          $links   entries stored as symbolic links
     */
    private function zip(array $entries, array $links = []): string
    {
        $file = $this->work . '/' . bin2hex(random_bytes(6)) . '.zip';
        $zip  = new ZipArchive();
        $zip->open($file, ZipArchive::CREATE);

        foreach ($entries as $name => $contents) {
            $zip->addFromString($name, $contents);

            if (in_array($name, $links, true)) {
                $zip->setExternalAttributesName($name, ZipArchive::OPSYS_UNIX, 0120777 << 16);
            }
        }

        $zip->close();

        return $file;
    }

    /**
     * A minisign signature (`Ed`: the file itself) with a trusted comment.
     */
    private static function sign(string $contents, string $secret, string $keyId): string
    {
        $signature = sodium_crypto_sign_detached($contents, $secret);
        $trusted   = 'timestamp:1700000000';
        $global    = sodium_crypto_sign_detached($signature . $trusted, $secret);

        return "untrusted comment: signature\n" . base64_encode('Ed' . $keyId . $signature) . "\ntrusted comment: {$trusted}\n" . base64_encode($global) . "\n";
    }
}
