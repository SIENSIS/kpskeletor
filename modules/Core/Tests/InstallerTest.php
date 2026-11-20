<?php

declare(strict_types=1);

namespace Modules\Core\Tests;

use CodeIgniter\Config\Factories;
use CodeIgniter\Shield\Models\UserModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Config\Database;
use Config\Services;
use Modules\Core\Config\Packages;
use Modules\Core\Install\Installer;
use Modules\Core\Install\InstallException;
use Modules\Core\Install\InstallOptions;
use Modules\Core\Install\InstallPrompt;
use Modules\Core\Install\InstallState;
use Modules\Core\Install\RuntimeConfig;
use Modules\Core\Modules\ModuleDiscovery;
use Modules\Core\Modules\ModuleSynchronizer;
use Modules\Core\Modules\NamespaceFile;

/**
 * The installation service end to end on SQLite (the test database), in temporary folders: a
 * fresh installation, its refusals, `.env` left to copy when the root is not writable, demo
 * data in dependency order and 2FA offered to the first superadmin.
 *
 * @internal
 */
final class InstallerTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use CleanDatabaseTrait;
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
        Services::resetSingle('storage'); // its upload rules were read from an earlier test's settings
        $this->saveInstallConfig();
    }

    protected function tearDown(): void
    {
        $this->restoreInstallConfig();
        parent::tearDown();
    }

    private function installer(?InstallState $state = null): Installer
    {
        $namespaces = $this->installRoot() . '/ModuleNamespaces.php';

        // The test database stays the connection: only the keys are applied to the process.
        $runtime = new class () extends RuntimeConfig {
            /** @var array<string, string> */
            public array $applied = [];

            public function apply(array $values): void
            {
                $this->applied = $values;

                if (isset($values['encryption.key'])) {
                    config('Encryption')->key = hex2bin(substr($values['encryption.key'], 8));
                    Services::resetSingle('encrypter');
                }
            }
        };

        return new Installer(
            $state ?? $this->installState(),
            runtime: $runtime,
            synchronizer: static fn (): ModuleSynchronizer => new ModuleSynchronizer(new ModuleDiscovery(), new NamespaceFile($namespaces)),
        );
    }

    /**
     * @param list<string>|null $demo
     */
    private function options(string $environment = 'production', ?array $demo = null): InstallOptions
    {
        return new InstallOptions(
            environment: $environment,
            baseURL: 'https://intranet.example.com/',
            dbDriver: 'SQLite3',
            dbName: 'site.db',
            adminUsername: 'owner',
            adminEmail: 'Owner@Example.com',
            adminPassword: self::PASSWORD,
            demo: $demo,
        );
    }

    public function testInstallsFromScratch(): void
    {
        $state  = $this->installState();
        $result = $this->installer($state)->install($this->options());

        // .env written with production and new keys.
        $this->assertTrue($result->envWritten);
        $env = (string) file_get_contents($state->envPath());
        $this->assertStringContainsString("CI_ENVIRONMENT = production\n", $env);
        $this->assertMatchesRegularExpression('/^encryption\.key = hex2bin:[0-9a-f]{64}$/m', $env);
        $this->assertMatchesRegularExpression('/^app\.maskKey = [0-9a-f]{64}$/m', $env);
        $this->assertStringContainsString("database.default.DBDriver = SQLite3\n", $env);
        $this->assertStringContainsString("database.default.database = site.db\n", $env);
        $this->assertStringContainsString("app.baseURL = https://intranet.example.com/\n", $env);
        // Web Push VAPID keys and contact (session 44, D-44-02).
        $this->assertMatchesRegularExpression('/^push\.vapidPublicKey = \'?[A-Za-z0-9_-]{87}\'?$/m', $env);
        $this->assertMatchesRegularExpression('/^push\.vapidPrivateKey = \'?[A-Za-z0-9_-]{43}\'?$/m', $env);
        $this->assertMatchesRegularExpression('/^push\.subject = \'?mailto:Owner@Example\.com\'?$/m', $env);

        // modules:sync ran.
        $this->assertTrue($this->db->tableExists('modules'));
        $this->assertGreaterThan(0, $this->db->table('permissions')->countAllResults());
        $this->assertNotSame([], $result->syncLines);

        // The first superadmin.
        $user = model(UserModel::class)->findById($result->superadminId);
        $this->assertNotNull($user);
        $this->assertSame('owner', $user->username);
        $this->assertSame('owner@example.com', $user->email);
        $this->assertTrue($user->inGroup('superadmin'));

        // Lock file, cron line, activity log; and now it is configured.
        $this->assertFileExists($state->lockPath());
        $this->assertSame('production', $state->lockData()['environment'] ?? null);
        $this->assertStringContainsString('spark schedule:run', $result->cronLine);
        $this->assertSame(1, $this->db->table('activity_log')->where('action', 'installed')->countAllResults());
        $this->assertSame(1, $this->db->table('activity_log')->where('action', 'superadmin_created')->countAllResults());
        $this->assertTrue($state->isConfigured());
    }

    public function testRootNotWritableLeavesTheEnvToCopy(): void
    {
        $state  = $this->installState(false);
        $result = $this->installer($state)->install($this->options());

        $this->assertFalse($result->envWritten);
        $this->assertFileDoesNotExist($state->envPath());
        $this->assertStringContainsString("CI_ENVIRONMENT = production\n", $result->env);
        $this->assertFileExists($state->lockPath());
        $this->assertTrue($state->isConfigured());
    }

    public function testRefusesWhenEnvExists(): void
    {
        file_put_contents($this->installRoot() . '/.env', "CI_ENVIRONMENT = development\n");

        $this->expectException(InstallException::class);
        $this->installer()->install($this->options());
    }

    public function testRefusesWhenLocked(): void
    {
        $this->installState()->writeLock(['installed_at' => 'yesterday']);

        try {
            $this->installer()->install($this->options(), keepEnv: true);
            $this->fail('Installed twice.');
        } catch (InstallException $e) {
            $this->assertSame(lang('Install.error.configured'), $e->getMessage());
        }
    }

    public function testRefusesADatabaseWithTables(): void
    {
        Database::forge('tests')->addField(['id' => ['type' => 'INT']])->createTable('leftover');

        try {
            $this->installer()->install($this->options());
            $this->fail('Installed into a database with tables.');
        } catch (InstallException $e) {
            $this->assertSame(lang('Install.error.notEmpty'), $e->getMessage());
        }

        $this->assertFileDoesNotExist($this->installState()->envPath());
        $this->assertFalse($this->installState()->isLocked());
    }

    public function testInvalidOptionsAreReportedPerField(): void
    {
        $options                = $this->options();
        $options->adminEmail    = 'not-an-email';
        $options->adminPassword = 'password';
        $options->dbName        = '../escape.db';
        $options->environment   = 'staging';

        try {
            $this->installer()->install($options);
            $this->fail('Invalid options accepted.');
        } catch (InstallException $e) {
            $this->assertArrayHasKey('adminEmail', $e->errors);
            $this->assertArrayHasKey('adminPassword', $e->errors);
            $this->assertArrayHasKey('dbName', $e->errors);
            $this->assertArrayHasKey('environment', $e->errors);
        }

        $this->assertFalse($this->db->tableExists('modules'));
    }

    public function testTheBackOfficePathIsWrittenToTheEnv(): void
    {
        $state                = $this->installState();
        $options              = $this->options();
        $options->backendPath = 'admin';
        $result               = $this->installer($state)->install($options);

        $this->assertTrue($result->envWritten);
        $this->assertStringContainsString("app.backendPath = admin\n", (string) file_get_contents($state->envPath()));
    }

    public function testTheDefaultBackOfficePathIsWrittenToo(): void
    {
        $state = $this->installState();
        $this->installer($state)->install($this->options());

        $this->assertStringContainsString("app.backendPath = backend\n", (string) file_get_contents($state->envPath()));
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function invalidBackendPaths(): iterable
    {
        yield 'too short' => ['a'];
        yield 'uppercase' => ['Admin'];
        yield 'two segments' => ['admin/panel'];
        yield 'starts with a digit' => ['1admin'];
        yield 'ends with a hyphen' => ['admin-'];
        yield 'dot' => ['admin.php'];
        yield 'too long' => [str_repeat('a', 31)];
        yield 'api' => ['api'];
        yield 'login' => ['login'];
        yield 'install' => ['install'];
        yield 'progress' => ['progress'];
        yield 'uploads' => ['uploads'];
        yield 'assets' => ['assets'];
        yield 'a language code' => ['es'];
        yield 'a module route' => ['news'];
        yield 'files' => ['files'];
        yield 'ical' => ['ical'];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('invalidBackendPaths')]
    public function testInvalidBackOfficePathsAreRefused(string $path): void
    {
        $options              = $this->options();
        $options->backendPath = $path;

        $this->assertArrayHasKey('backendPath', $this->installer()->validate($options, false));
    }

    public function testValidBackOfficePathsAreAccepted(): void
    {
        foreach (['admin', 'private', 'intranet', 'gestio-2', 'backend'] as $path) {
            $options              = $this->options();
            $options->backendPath = $path;

            $this->assertArrayNotHasKey('backendPath', $this->installer()->validate($options, false), $path);
        }
    }

    public function testCommonPasswordsAreRefusedByShieldsRules(): void
    {
        $options                = $this->options();
        $options->adminPassword = 'password123';

        $this->assertArrayHasKey('adminPassword', $this->installer()->validate($options));
    }

    public function testDemoDataIsRefusedInProduction(): void
    {
        $this->assertArrayHasKey('demo', $this->installer()->validate($this->options('production', [])));
        $this->assertArrayNotHasKey('demo', $this->installer()->validate($this->options('development', [])));
    }

    public function testDemoInProductionIsDecidedByTheConfigurationNotTheMissingDatabase(): void
    {
        // A fresh clone has no database yet: the installer must not read the settings table
        // (it crashed with "Unable to connect" before); here the test database has no tables.
        $this->assertArrayHasKey('demo', $this->installer()->validate($this->options('production', [])));

        $packages                        = new Packages();
        $packages->allowDemoInProduction = true;
        Factories::injectMock('config', Packages::class, $packages);

        try {
            $this->assertArrayNotHasKey('demo', $this->installer()->validate($this->options('production', [])));
        } finally {
            Factories::reset('config');
        }
    }

    public function testDemoOfAModuleLoadsTheModulesItNeedsFirst(): void
    {
        $result = $this->installer()->install($this->options('development', ['News']));

        $this->assertSame(['Auth', 'News'], $result->demoLoaded);
        $this->assertGreaterThan(0, $this->db->table('news')->countAllResults());
    }

    public function testDemoUsersGetRandomPasswordsShownOnce(): void
    {
        $result = $this->installer()->install($this->options('development', ['Auth']));

        // One random password per demo user, returned once to the installer, never fixed.
        $this->assertSame(['superadmin@example.com', 'admin@example.com', 'editor@example.com', 'usuari@example.com'], array_keys($result->demoSecrets));
        $this->assertCount(4, array_unique($result->demoSecrets));

        foreach ($result->demoSecrets as $email => $password) {
            $this->assertMatchesRegularExpression('/^[A-Za-z2-9]{5}(-[A-Za-z2-9]{5}){3}$/', $password);
            $user = model(UserModel::class)->findByCredentials(['email' => $email]);
            $this->assertNotNull($user, $email);
            $this->assertTrue(service('passwords')->verify($password, (string) $user->password_hash), $email);
        }

        // Not written to the activity log.
        foreach ($this->db->table('activity_log')->get()->getResultArray() as $row) {
            foreach ($result->demoSecrets as $password) {
                $this->assertStringNotContainsString($password, json_encode($row, JSON_THROW_ON_ERROR));
            }
        }
    }

    public function testDemoUsersNeverReplaceTheSuperadmin(): void
    {
        $options                = $this->options('development', []);
        $options->adminUsername = 'admin';

        $result = $this->installer()->install($options);

        $this->assertSame('', $result->demoError);
        $this->assertContains('Auth', $result->demoLoaded);
        $admin = model(UserModel::class)->findById($result->superadminId);
        $this->assertSame('owner@example.com', $admin?->email);
        $this->assertSame(1, model(UserModel::class)->where('username', 'admin')->countAllResults());
    }

    public function testAFailedDemoDoesNotUndoTheInstallation(): void
    {
        $result = $this->installer()->install($this->options('development', ['Nonexistent']));

        $this->assertNotSame('', $result->demoError);
        $this->assertSame([], $result->demoLoaded);
        $this->assertTrue($this->installState()->isConfigured());
    }

    public function testTheSuperadminIsOfferedTwoFactor(): void
    {
        $prompt = new class () implements InstallPrompt {
            /** @var list<string> */
            public array $lines = [];

            public function interactive(): bool
            {
                return false;
            }

            public function option(string $name): ?string
            {
                return $name === '2fa' ? 'email' : null;
            }

            public function ask(string $question, array $options = [], ?string $default = null): string
            {
                return $default ?? ($options[0] ?? '');
            }

            public function write(string $line, ?string $color = null): void
            {
                $this->lines[] = $line;
            }
        };

        $result = $this->installer()->install($this->options(), $prompt);

        $row = $this->db->table('user_two_factor')->where('user_id', $result->superadminId)->get()->getRowArray();
        $this->assertSame('email', $row['method'] ?? null);
        $this->assertSame(10, $this->db->table('user_recovery_codes')->where('user_id', $result->superadminId)->countAllResults());
        $this->assertContains(lang('Auth.install.recovery'), $prompt->lines);
    }
}
