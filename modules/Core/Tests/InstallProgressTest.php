<?php

declare(strict_types=1);

namespace Modules\Core\Tests;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Config\Database;
use Config\Services;
use Modules\Core\Install\Installer;
use Modules\Core\Install\InstallException;
use Modules\Core\Install\InstallJob;
use Modules\Core\Install\InstallJobs;
use Modules\Core\Install\InstallOptions;
use Modules\Core\Install\InstallState;
use Modules\Core\Install\RuntimeConfig;
use Modules\Core\Modules\ModuleDiscovery;
use Modules\Core\Modules\ModuleSynchronizer;
use Modules\Core\Modules\NamespaceFile;
use Modules\Core\Security\Check\SecurityChecker;

/**
 * The installer's steps (session 39): reported in order to a listener, the failing step, and a
 * whole web job (InstallJob) with its progress, log and one-time result, on SQLite.
 *
 * @internal
 */
final class InstallProgressTest extends CIUnitTestCase
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
        Services::resetSingle('storage');
        $this->saveInstallConfig();
    }

    protected function tearDown(): void
    {
        $this->restoreInstallConfig();
        parent::tearDown();
    }

    private function installer(InstallState $state): Installer
    {
        $namespaces = $this->installRoot() . '/ModuleNamespaces.php';

        // The test database stays the connection: only the keys are applied to the process.
        $runtime = new class () extends RuntimeConfig {
            public function apply(array $values): void
            {
                if (isset($values['encryption.key'])) {
                    config('Encryption')->key = hex2bin(substr($values['encryption.key'], 8));
                    Services::resetSingle('encrypter');
                }
            }
        };

        return new Installer(
            $state,
            runtime: $runtime,
            synchronizer: static fn (): ModuleSynchronizer => new ModuleSynchronizer(new ModuleDiscovery(), new NamespaceFile($namespaces)),
        );
    }

    private function options(): InstallOptions
    {
        return new InstallOptions(
            environment: 'production',
            baseURL: 'https://intranet.example.com/',
            dbDriver: 'SQLite3',
            dbName: 'site.db',
            adminUsername: 'owner',
            adminEmail: 'owner@example.com',
            adminPassword: self::PASSWORD,
        );
    }

    private function listener(): RecordingInstallProgress
    {
        return new RecordingInstallProgress();
    }

    public function testStepsAreReportedInOrder(): void
    {
        $listener = $this->listener();
        $this->installer($this->installState(false))->install($this->options(), null, false, $listener);

        $codes = [];

        foreach ($listener->steps as [$code]) {
            if (end($codes) !== $code) {
                $codes[] = $code;
            }
        }

        $this->assertSame(['checks', 'env', 'database', 'migrations', 'modules', 'permissions', 'settings', 'assets', 'groups', 'api', 'superadmin', 'envFile', 'lock'], $codes);
        $this->assertSame([], $listener->failures);
        $this->assertSame(['Install.progress.envToCopy'], $listener->logs);

        // "n of m" for the migrations, one per namespace, the last one complete.
        $migrations = array_values(array_filter($listener->steps, static fn (array $step): bool => $step[0] === 'migrations'));
        $this->assertGreaterThan(3, count($migrations));
        $last = end($migrations);
        $this->assertSame($last[1], $last[2]);
        $this->assertContains('Modules\Core', array_map(static fn (array $step): string => (string) ($step[3]['namespace'] ?? ''), $migrations));

        // Never a secret in a step's parameters.
        $this->assertStringNotContainsString(self::PASSWORD, json_encode($listener->steps, JSON_THROW_ON_ERROR));
    }

    public function testTheFailingStepIsReported(): void
    {
        Database::forge('tests')->addField(['id' => ['type' => 'INT']])->createTable('leftover');
        $listener = $this->listener();

        try {
            $this->installer($this->installState())->install($this->options(), null, false, $listener);
            $this->fail('The installation should stop.');
        } catch (InstallException $e) {
            $this->assertSame(lang('Install.error.notEmpty'), $e->getMessage());
        }

        $this->assertSame(['checks', 'env', 'database'], array_column($listener->steps, 0));
        $this->assertSame([['database', lang('Install.error.notEmpty')]], $listener->failures);
    }

    public function testJobRecordsProgressLogAndOneTimeResult(): void
    {
        $state = $this->installState(false);
        $jobs  = new InstallJobs($state);
        $id    = $jobs->create('en');
        $this->assertTrue($jobs->lock($id));

        $job    = new InstallJob($jobs, $this->installer($state), static fn (): SecurityChecker => new SecurityChecker([]));
        $result = $job->run($id, $this->options(), ['loginUrl' => 'https://intranet.example.com/login', 'username' => 'owner']);

        $this->assertNotNull($result);
        $data = $jobs->get($id);
        $this->assertNotNull($data);
        $this->assertSame('done', $data['status']);
        $this->assertSame('done', $data['step']);
        $this->assertSame(100, $data['percent']);
        $this->assertFalse($jobs->isLocked(), 'The lock is released.');

        $keys = array_column($data['log'], 'key');
        $this->assertContains('Install.step.migrations', $keys);
        $this->assertContains('Install.progress.securitySummary', $keys);
        $this->assertSame('Install.step.done', end($keys));

        // The .env content (keys) only in the one-time file.
        $this->assertStringNotContainsString('encryption.key', (string) file_get_contents($jobs->file($id)));
        $this->assertStringContainsString('encryption.key = hex2bin:', (string) ($jobs->takeSecrets($id)['env'] ?? ''));
        $this->assertNull($jobs->takeSecrets($id));
    }

    public function testFailedJobKeepsTheStepAndReleasesTheLock(): void
    {
        Database::forge('tests')->addField(['id' => ['type' => 'INT']])->createTable('leftover');
        $state = $this->installState();
        $jobs  = new InstallJobs($state);
        $id    = $jobs->create('en');
        $jobs->lock($id);

        $this->assertNull((new InstallJob($jobs, $this->installer($state)))->run($id, $this->options(), ['loginUrl' => '', 'username' => 'owner']));

        $data = $jobs->get($id);
        $this->assertNotNull($data);
        $this->assertSame('failed', $data['status']);
        $this->assertSame('database', $data['step']);
        $this->assertSame(lang('Install.error.notEmpty'), $data['message']);
        $this->assertFalse($jobs->isLocked());
        $this->assertFileDoesNotExist($jobs->secretsFile($id));
    }
}
