<?php

declare(strict_types=1);

namespace Modules\Core\Tests;

use Closure;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\Shield\Models\UserModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use CodeIgniter\Test\TestResponse;
use Config\Database;
use Config\Services;
use Modules\Core\Filters\InstallGuard;

/**
 * The web installer (`install`) and the `installguard` filter, with an unconfigured
 * installation in temporary folders: the form, its refusals (404 once configured or with a
 * non-empty database), the HTTPS warning, a whole installation with the `.env` content shown
 * when the root is not writable, and every other page redirected to the installer meanwhile.
 *
 * @internal
 */
final class WebInstallerTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use CleanDatabaseTrait;
    use FeatureTestTrait;
    use InstallFixturesTrait;

    protected $migrate   = false;
    protected $refresh   = false;
    protected $namespace = null;

    private const PASSWORD = 'Tramuntana-Garbi-2026!';

    protected function setUp(): void
    {
        parent::setUp();
        $this->dropAllTables();
        service('language')->setLocale('en');
        Services::resetSingle('settings');
        $this->saveInstallConfig();
    }

    protected function tearDown(): void
    {
        $this->restoreInstallConfig();
        Services::resetSingle('installstate');
        parent::tearDown();
    }

    private function unconfigured(bool $rootWritable = true): void
    {
        Services::injectMock('installstate', $this->installState($rootWritable));
    }

    /**
     * @param array<string, string> $fields
     */
    private function submit(array $fields = []): TestResponse
    {
        $params = $fields + [
            'environment'          => 'production',
            'baseURL'              => '',
            'dbDriver'             => 'SQLite3',
            'dbName'               => 'site.db',
            'dbPrefix'             => '',
            'adminUsername'        => 'owner',
            'adminEmail'           => 'owner@example.com',
            'adminPassword'        => self::PASSWORD,
            'adminPasswordConfirm' => self::PASSWORD,
            'csrf_test_name'       => 'test-csrf-token',
        ];

        Services::resetSingle('security');

        return $this->withSession(['csrf_test_name' => 'test-csrf-token'])->post('install', $params);
    }

    /**
     * The status of a request; 404 when the controller throws PageNotFoundException.
     *
     * @param Closure(): TestResponse $request
     */
    private function statusOf(Closure $request): int
    {
        try {
            return $request()->response()->getStatusCode();
        } catch (PageNotFoundException) {
            return 404;
        }
    }

    public function testFormIsShownBeforeInstallation(): void
    {
        $this->unconfigured();

        $response = $this->get('install');

        $response->assertOK();
        $response->assertSee(lang('Install.web.title'));
        $response->assertSee('adminPasswordConfirm');
        $response->assertHeader('Cache-Control');
        // Tests run over plain HTTP: the warning is shown.
        $response->assertSee(lang('Install.web.httpsTitle'));
    }

    public function testNotFoundOnceConfigured(): void
    {
        $this->installState()->writeLock(['installed_at' => 'now']);
        $this->unconfigured();

        $this->assertSame(404, $this->statusOf(fn (): TestResponse => $this->get('install')));
    }

    public function testNotFoundWithEnvFile(): void
    {
        file_put_contents($this->installRoot() . '/.env', "CI_ENVIRONMENT = production\n");
        $this->unconfigured();

        $this->assertSame(404, $this->statusOf(fn (): TestResponse => $this->get('install')));
    }

    public function testNotFoundWhenTheDatabaseHasTables(): void
    {
        $this->unconfigured();
        Database::forge('tests')->addField(['id' => ['type' => 'INT']])->createTable('leftover');

        $this->assertSame(404, $this->statusOf(fn (): TestResponse => $this->get('install')));
        $this->assertSame(404, $this->statusOf(fn (): TestResponse => $this->submit()));
    }

    public function testInvalidValuesShowTheFormWithoutThePasswords(): void
    {
        $this->unconfigured();

        $response = $this->submit(['adminEmail' => 'nope', 'adminPasswordConfirm' => 'different-' . self::PASSWORD]);

        $response->assertOK();
        $response->assertSee(lang('Install.invalid.email'));
        $response->assertSee(lang('Install.invalid.passwordConfirm'));
        $response->assertDontSee(self::PASSWORD);
        $this->assertFalse($this->db->tableExists('modules'));
        $this->assertFalse($this->installState()->isConfigured());
    }

    public function testInstallsAndShowsTheEnvWhenTheRootIsNotWritable(): void
    {
        $this->unconfigured(false);

        $form = $this->get('install');
        $form->assertSee(lang('Install.web.rootNotWritable', [$this->installState(false)->envPath()]));

        $response = $this->submit();

        $response->assertOK();
        $response->assertSee(lang('Install.web.doneTitle'));
        $response->assertSee('CI_ENVIRONMENT = production');
        $response->assertSee('encryption.key = hex2bin:');
        $response->assertSee('spark schedule:run');

        $user = model(UserModel::class)->findByCredentials(['email' => 'owner@example.com']);
        $this->assertNotNull($user);
        $this->assertTrue($user->inGroup('superadmin'));

        $state = $this->installState(false);
        $this->assertFileExists($state->lockPath());
        $this->assertFileDoesNotExist($state->envPath());

        // Configured now: the installer is gone.
        $this->assertSame(404, $this->statusOf(fn (): TestResponse => $this->get('install')));
    }

    public function testInstallWritesTheEnvWhenTheRootIsWritable(): void
    {
        $this->unconfigured();

        $response = $this->submit();

        $response->assertOK();
        $response->assertSee(lang('Install.web.envWritten', [$this->installState()->envPath()]));
        $response->assertDontSee('encryption.key = hex2bin:');
        $this->assertFileExists($this->installState()->envPath());
    }

    public function testEveryOtherPageRedirectsToTheInstaller(): void
    {
        $this->unconfigured();

        $response = $this->get('backend');

        $response->assertStatus(302);
        $this->assertStringEndsWith('/install', $response->response()->getHeaderLine('Location'));
    }

    public function testGuardDoesNothingOnceConfigured(): void
    {
        $this->installState()->writeLock(['installed_at' => 'now']);
        $this->unconfigured();

        $this->assertNull((new InstallGuard())->before(service('request')));
    }

    public function testInstallerPaths(): void
    {
        $this->assertTrue(InstallGuard::isInstallerPath('install'));
        $this->assertTrue(InstallGuard::isInstallerPath('/install/'));
        $this->assertFalse(InstallGuard::isInstallerPath('installer'));
        $this->assertFalse(InstallGuard::isInstallerPath('backend/install'));
    }
}
