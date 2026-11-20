<?php

declare(strict_types=1);

namespace Modules\Devtools\Tests;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\StreamFilterTrait;
use Modules\Core\Maintenance\MaintenanceMode;
use Modules\Core\Models\ModuleModel;
use Modules\Core\Modules\ModuleGenerator;
use Modules\Core\Modules\ModuleToggler;
use Modules\Core\Modules\SyncReport;
use Modules\Core\Packages\PackageBackup;
use Modules\Core\Packages\PackageInfo;
use Modules\Core\Packages\PackageInstaller;
use Modules\Core\Packages\PackageValidator;
use Modules\Core\Packages\Safeguards;
use Modules\Core\Security\Minisign;
use Modules\Core\Tests\CleanDatabaseTrait;
use Modules\Core\Tests\ModuleFixturesTrait;
use Modules\Devtools\Commands\ModulesPackage;
use Modules\Devtools\Packages\PackageBuilder;
use Modules\Devtools\Packages\PackageBuildFailed;
use Modules\Devtools\Packages\PackageSource;
use Modules\Devtools\Packages\PackageStore;
use Modules\Devtools\Packages\VersionBump;
use ZipArchive;

/**
 * Building packages (session 43): the Tasks module's package validates as the installer
 * validates it; a generated module's package installs with the installer of `modules:install`;
 * dotfiles, `node_modules`, `writable`, editor files and refused extensions are left out; a
 * symbolic link or a PHP syntax error stops the build (nothing left behind); themes; raising
 * the version.
 *
 * @internal
 */
final class PackageBuilderTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use CleanDatabaseTrait;
    use ModuleFixturesTrait;
    use StreamFilterTrait;

    protected $migrate   = false;
    protected $refresh   = false;
    protected $namespace = null;

    private string $work = '';

    protected function setUp(): void
    {
        parent::setUp();
        $this->dropAllTables();
        service('language')->setLocale('en');
        $this->work = sys_get_temp_dir() . '/kps-devpkg-' . bin2hex(random_bytes(6));
        mkdir($this->work . '/out', 0777, true);
        mkdir($this->work . '/src/modules', 0777, true);
        mkdir($this->work . '/src/themes', 0777, true);
        PackageStore::$testFolder = $this->work . '/out';
        $this->synchronizer()->sync();
    }

    protected function tearDown(): void
    {
        PackageStore::$testFolder         = null;
        ModulesPackage::$testEnvironment = null;
        ModulesPackage::$testPassword    = null;
        PackageValidator::removeFolder($this->work);
        @unlink($this->work . '-maintenance.json');
        $this->removeTempRoot();
        parent::tearDown();
    }

    public function testTheTasksPackageValidatesAsTheInstallerValidatesIt(): void
    {
        $source = PackageSource::find(PackageInfo::MODULE, 'Tasks');
        $result = (new PackageBuilder(null, false))->build($source);

        $this->assertSame($this->work . '/out/Tasks-' . $source->version . '.zip', $result->zip);
        $info = (new PackageValidator())->validateAndLint($result->zip, null, PackageInfo::MODULE, $this->work);
        $this->assertSame('Tasks', $info->name);
        $this->assertSame($source->version, $info->version);
        $this->assertContains('tasks-tasks.list', $info->permissions);
        $this->assertArrayHasKey('Config/Module.php', $info->files);
        $this->assertArrayHasKey('CHANGELOG.md', $info->files);

        // One top folder; sha256sum format.
        $zip = new ZipArchive();
        $zip->open($result->zip);

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $this->assertStringStartsWith('Tasks/', (string) $zip->getNameIndex($i));
        }

        $zip->close();
        $this->assertSame(hash_file('sha256', $result->zip) . '  Tasks-' . $source->version . ".zip\n", file_get_contents($result->zip . '.sha256'));

        // The installer's plan for an empty checkout has no problem (dependencies are installed).
        $this->assertSame([], $this->installer()->plan($info)['problems']);
    }

    public function testAGeneratedModulesPackageInstalls(): void
    {
        $name = $this->uniqueModuleName();
        (new ModuleGenerator($this->work . '/src/modules'))->generate($name);
        $result = (new PackageBuilder(null, false))->build(PackageSource::find(PackageInfo::MODULE, $name, $this->work . '/src'));

        $info   = (new PackageValidator())->validate($result->zip);
        $action = $this->installer()->install($result->zip, $info, ['enable' => true]);

        $this->assertSame('install', $action['action']);
        $this->assertFileExists($this->tempRoot() . '/' . $name . '/Config/Module.php');
        $this->assertTrue(model(ModuleModel::class, false)->allByName()[$name]['enabled']);
    }

    public function testLeftOutFilesAndFolders(): void
    {
        $name   = $this->uniqueModuleName();
        $folder = $this->work . '/src/modules/' . $name;
        (new ModuleGenerator($this->work . '/src/modules'))->generate($name);

        foreach ([
            '.env', '.idea/workspace.xml', '.git/config', 'node_modules/x/index.js', 'writable/cache.json',
            'Views/index.php~', 'Views/.index.php.swp', 'Views/index.php.bak', 'Thumbs.db',
            'tools/build.sh', 'tools/run.phar', 'Assets/css/site.scss', 'notes.txt',
        ] as $file) {
            @mkdir(dirname($folder . '/' . $file), 0777, true);
            file_put_contents($folder . '/' . $file, 'x');
        }

        $result = (new PackageBuilder(null, false))->build(PackageSource::find(PackageInfo::MODULE, $name, $this->work . '/src'));
        $files  = array_keys($result->info->files);

        $this->assertContains('notes.txt', $files);
        $this->assertContains('Config/Module.php', $files);

        foreach ($files as $file) {
            $this->assertDoesNotMatchRegularExpression('#(^|/)\.|node_modules|writable|~$|\.bak$|\.sh$|\.phar$|\.scss$|Thumbs#', $file);
        }

        // Refused extensions and editor files are reported; dot entries are silent.
        $this->assertSame(['Assets/css/site.scss', 'Thumbs.db', 'Views/index.php.bak', 'Views/index.php~', 'tools/build.sh', 'tools/run.phar'], $result->skipped);
    }

    public function testAPackageWithAForbiddenFileIsNotBuilt(): void
    {
        $name   = $this->uniqueModuleName();
        $folder = $this->work . '/src/modules/' . $name;
        (new ModuleGenerator($this->work . '/src/modules'))->generate($name);
        $source = PackageSource::find(PackageInfo::MODULE, $name, $this->work . '/src');

        // A symbolic link.
        symlink('/etc/passwd', $folder . '/passwd.txt');
        $this->assertBuildFails($source, lang('Devtools.packages.errors.link', ['passwd.txt']));
        unlink($folder . '/passwd.txt');

        // A PHP file the installer's `php -l` refuses.
        file_put_contents($folder . '/Controllers/Broken.php', "<?php\nfunction (\n");
        $this->assertBuildFails($source, null);
        unlink($folder . '/Controllers/Broken.php');

        // A manifest the installer refuses (reserved name in the folder of another module).
        file_put_contents($folder . '/Config/Module.php', str_replace("'0.1.0'", "'one'", (string) file_get_contents($folder . '/Config/Module.php')));
        $this->assertBuildFails(PackageSource::find(PackageInfo::MODULE, $name, $this->work . '/src'), lang('Devtools.packages.errors.invalid', [lang('Core.packages.version')]));
    }

    public function testCoreModulesAndUnknownNamesAreNotPackaged(): void
    {
        foreach ([...ModuleToggler::CORE_MODULES, 'Nope', '../Tasks', 'tasks'] as $name) {
            try {
                PackageSource::find(PackageInfo::MODULE, $name);
                $this->fail($name);
            } catch (PackageBuildFailed $e) {
                $this->assertSame(lang('Devtools.packages.errors.noSource', [$name]), $e->getMessage());
            }
        }

        $names = array_map(static fn (PackageSource $s): string => $s->type . ':' . $s->name, PackageSource::all());
        $this->assertContains('module:Tasks', $names);
        $this->assertContains('theme:default', $names);
        $this->assertNotContains('module:Auth', $names);
    }

    public function testAThemePackage(): void
    {
        $result = (new PackageBuilder(null, false))->build(PackageSource::find(PackageInfo::THEME, 'default'));
        $info   = (new PackageValidator())->validateAndLint($result->zip, null, PackageInfo::THEME, $this->work);

        $this->assertSame('default', $info->name);
        $this->assertArrayHasKey('Theme.php', $info->files);
        $this->assertStringEndsWith('/default-' . $info->version . '.zip', $result->zip);
    }

    public function testARebuildForgetsTheOldSignature(): void
    {
        $source = PackageSource::find(PackageInfo::THEME, 'default');
        $zip    = (new PackageBuilder(null, false))->build($source)->zip;
        file_put_contents($zip . '.minisig', 'old');
        file_put_contents($zip . '.json', '{}');

        (new PackageBuilder(null, false))->build($source);
        $this->assertFileDoesNotExist($zip . '.minisig');
        $this->assertFileDoesNotExist($zip . '.json');
        $this->assertSame([], glob($this->work . '/out/.*tmp') ?: [], 'No temporary file is left.');
    }

    public function testUncommittedChangesAreAWarningOnly(): void
    {
        $name = $this->uniqueModuleName();
        (new ModuleGenerator($this->work . '/src/modules'))->generate($name);

        if (! $this->git(['init', '-q', $this->work . '/src'])) {
            $this->markTestSkipped('git is not available.');
        }

        $result = (new PackageBuilder())->build(PackageSource::find(PackageInfo::MODULE, $name, $this->work . '/src'));
        $this->assertCount(1, $result->warnings);
        $this->assertStringContainsString('not committed', $result->warnings[0]);
        $this->assertFileExists($result->zip);
    }

    public function testRaisingTheVersion(): void
    {
        $name   = $this->uniqueModuleName();
        $folder = $this->work . '/src/modules/' . $name;
        (new ModuleGenerator($this->work . '/src/modules'))->generate($name);
        $source = PackageSource::find(PackageInfo::MODULE, $name, $this->work . '/src');
        $this->assertSame('0.1.0', $source->version);

        $this->assertSame('0.1.1', (new VersionBump())->raise($source, 'patch'));
        $this->assertSame('0.2.0', (new VersionBump())->raise(PackageSource::find(PackageInfo::MODULE, $name, $this->work . '/src'), 'minor'));
        $this->assertSame('1.0.0', (new VersionBump())->raise(PackageSource::find(PackageInfo::MODULE, $name, $this->work . '/src'), 'major'));
        $this->assertSame('1.0.0', PackageSource::find(PackageInfo::MODULE, $name, $this->work . '/src')->version);

        $changelog = (string) file_get_contents($folder . '/CHANGELOG.md');
        $this->assertLessThan(strpos($changelog, '## 0.2.0'), strpos($changelog, '## 1.0.0'));
        $this->assertStringContainsString('## 0.1.1', $changelog);

        // An Unreleased section becomes the new version's.
        $this->assertSame("# C\n\n## [Unreleased]\n\n## 1.1.0\n\n- Added x.\n", VersionBump::changelog("# C\n\n## [Unreleased]\n\n- Added x.\n", 'X', '1.1.0'));

        $this->expectException(PackageBuildFailed::class);
        (new VersionBump())->raise($source, 'huge');
    }

    public function testTheCommand(): void
    {
        $fixtures = dirname(__DIR__) . '/Tests/Fixtures/minisign/';
        $out      = $this->work . '/cli';

        // Development only.
        command('modules:package Tasks');
        $this->assertStringContainsString(lang('Devtools.packages.cli.developmentOnly'), $this->getStreamFilterBuffer());
        $this->assertSame([], glob($this->work . '/out/*') ?: []);

        ModulesPackage::$testEnvironment = 'development';
        $version                         = PackageSource::find(PackageInfo::MODULE, 'Tasks')->version;

        // Not signed: the minisign command to sign elsewhere.
        $this->resetStreamFilterBuffer();
        command('modules:package Tasks --out=' . $out);
        $output = $this->getStreamFilterBuffer();
        $this->assertMatchesRegularExpression('/Valid package: Tasks ' . preg_quote($version, '/') . ', \d+ files/', $output);
        $this->assertStringContainsString("-t 'Tasks {$version}'", $output);
        $this->assertFileExists($out . "/Tasks-{$version}.zip.sha256");
        $this->assertFileDoesNotExist($out . "/Tasks-{$version}.zip.minisig");

        // Signed with an unencrypted minisign key; the minisign public key verifies it.
        $this->resetStreamFilterBuffer();
        command('modules:package default --theme --out=' . $out . ' --sign=' . $fixtures . 'plain.key');
        $output  = $this->getStreamFilterBuffer();
        $theme   = PackageSource::find(PackageInfo::THEME, 'default')->version;
        $zip     = $out . "/default-{$theme}.zip";
        (new Minisign([(string) file_get_contents($fixtures . 'plain.pub')]))->verify($zip, (string) file_get_contents($zip . '.minisig'));
        $this->assertStringContainsString(trim(explode("\n", (string) file_get_contents($fixtures . 'plain.pub'))[1]), $output);
        $this->assertSame(1, db_connect()->table('activity_log')->where(['module' => 'Devtools', 'action' => 'signed'])->countAllResults());

        // A wrong password.
        [$key] = PackageSigningTest::makeKey('right');
        file_put_contents($this->work . '/test.key', $key);
        ModulesPackage::$testPassword = 'wrong';
        $this->resetStreamFilterBuffer();
        command('modules:package Tasks --out=' . $out . ' --sign=' . $this->work . '/test.key');
        $this->assertStringContainsString(lang('Devtools.packages.sign.errors.password'), $this->getStreamFilterBuffer());
        $this->assertFileDoesNotExist($out . "/Tasks-{$version}.zip.minisig");
    }

    private function assertBuildFails(PackageSource $source, ?string $message): void
    {
        try {
            (new PackageBuilder(null, false))->build($source);
            $this->fail('The package was built.');
        } catch (PackageBuildFailed $e) {
            if ($message !== null) {
                $this->assertSame($message, $e->getMessage());
            }
        }

        $this->assertSame([], array_diff(scandir($this->work . '/out') ?: [], ['.', '..']), 'Nothing is left behind.');
    }

    /**
     * @param list<string> $arguments
     */
    private function git(array $arguments): bool
    {
        $process = @proc_open(['git', ...$arguments], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);

        if (! is_resource($process)) {
            return false;
        }

        stream_get_contents($pipes[1]);
        stream_get_contents($pipes[2]);

        return proc_close($process) === 0;
    }

    private function installer(): PackageInstaller
    {
        return new PackageInstaller(
            new PackageValidator(),
            new Safeguards(new MaintenanceMode($this->work . '-maintenance.json'), static function (PackageBackup $request): void {
                $request->done('test.kpbackup');
            }),
            fn (): SyncReport => $this->synchronizer()->sync(),
            new ModuleToggler($this->discovery()),
            $this->tempRoot(),
            $this->work . '/themes',
            $this->work,
        );
    }
}
