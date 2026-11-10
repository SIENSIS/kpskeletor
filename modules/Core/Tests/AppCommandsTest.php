<?php

declare(strict_types=1);

namespace Modules\Core\Tests;

use CodeIgniter\CLI\CLI;
use CodeIgniter\Events\Events;
use CodeIgniter\Shield\Models\UserModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\Mock\MockInputOutput;
use Config\Services;
use Modules\Core\Cache\CacheClearer;
use Modules\Core\Cache\CoreCache;
use Modules\Core\Modules\CacheInvalidator;

/**
 * `app:install` without questions (options + INSTALL_ADMIN_PASSWORD), `about` and
 * `app:cache-clear`.
 *
 * @internal
 */
final class AppCommandsTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use CleanDatabaseTrait;
    use InstallFixturesTrait;

    protected $migrate   = false;
    protected $refresh   = false;
    protected $namespace = null;

    private MockInputOutput $io;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dropAllTables();
        service('language')->setLocale('en');
        Services::resetSingle('settings');
        $this->saveInstallConfig();
        Services::injectMock('installstate', $this->installState());

        $this->io = new MockInputOutput();
        CLI::setInputOutput($this->io);
    }

    protected function tearDown(): void
    {
        CLI::resetInputOutput();
        putenv('INSTALL_ADMIN_PASSWORD');
        Services::resetSingle('installstate');
        $this->restoreInstallConfig();
        parent::tearDown();
    }

    private function spark(string $command): string
    {
        command($command);

        return $this->io->getOutput();
    }

    public function testInstallWithOptions(): void
    {
        putenv('INSTALL_ADMIN_PASSWORD=Tramuntana-Garbi-2026!');

        $output = $this->spark('app:install --no-interaction --db=sqlite --db-name=site.db --admin=owner --email=owner@example.com --2fa=none');

        $this->assertStringContainsString(lang('Install.cli.done', [$this->installState()->lockPath()]), $output);
        $this->assertStringContainsString('spark schedule:run', $output);
        $this->assertFileExists($this->installState()->envPath());
        $this->assertStringContainsString("CI_ENVIRONMENT = production\n", (string) file_get_contents($this->installState()->envPath()));
        $this->assertTrue(model(UserModel::class)->findByCredentials(['email' => 'owner@example.com'])?->inGroup('superadmin'));

        // A second run is refused.
        $this->io = new MockInputOutput();
        CLI::setInputOutput($this->io);
        $this->assertStringContainsString(lang('Install.error.configured'), $this->spark('app:install --no-interaction'));
    }

    public function testInstallWithoutAPasswordStops(): void
    {
        $output = $this->spark('app:install --no-interaction --db=sqlite --db-name=site.db --email=owner@example.com');

        $this->assertStringContainsString('adminPassword', $output);
        $this->assertFalse($this->installState()->isConfigured());
        $this->assertFalse($this->db->tableExists('modules'));
    }

    public function testAnExistingEnvIsNotOverwrittenWithoutKeepEnv(): void
    {
        file_put_contents($this->installState()->envPath(), "CI_ENVIRONMENT = development\n");

        $output = $this->spark('app:install --no-interaction');

        $this->assertStringContainsString(lang('Install.cli.envExists'), $output);
        $this->assertSame("CI_ENVIRONMENT = development\n", file_get_contents($this->installState()->envPath()));
    }

    public function testAboutNeverPrintsSecrets(): void
    {
        $output = $this->spark('about');

        $this->assertStringContainsString('CodeIgniter', $output);
        $this->assertStringContainsString('app.maskKey', $output);
        $this->assertStringNotContainsString((string) config('App')->maskKey, $output);
        $this->assertStringContainsString(lang('Install.about.notInstalled'), $output);
    }

    public function testCacheClearClearsEverythingAndFiresTheEvent(): void
    {
        $fired = [];
        Events::on(CacheClearer::EVENT, static function () use (&$fired): void {
            $fired[] = CacheClearer::EVENT;
        });
        Events::on(CacheInvalidator::EVENT, static function () use (&$fired): void {
            $fired[] = CacheInvalidator::EVENT;
        });

        cache()->save('kps-test-entry', 'value', 300);
        $calls = 0;
        (new CoreCache())->remember('menus', 'test', static function () use (&$calls): int {
            return ++$calls;
        });

        $output = $this->spark('app:cache-clear');

        $this->assertStringContainsString(lang('Install.cache.cleared', [config('Cache')->handler]), $output);
        $this->assertNull(cache('kps-test-entry'));
        $this->assertSame([CacheInvalidator::EVENT, CacheClearer::EVENT], $fired);
        (new CoreCache())->remember('menus', 'test', static function () use (&$calls): int {
            return ++$calls;
        });
        $this->assertSame(2, $calls);
    }
}
