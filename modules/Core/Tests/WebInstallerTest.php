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
use Modules\Core\Controllers\WebInstaller;
use Modules\Core\Filters\InstallGuard;
use Modules\Core\Install\InstallJobs;
use Modules\Core\Security\RateLimiter;

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
            'backendPath'          => 'backend',
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

    public function testTheFormAsksForTheBackOfficePath(): void
    {
        $this->unconfigured();

        $response = $this->get('install');

        $this->assertStringContainsString('name="backendPath"', (string) $response->getBody());
        $response->assertSee(lang('Install.form.backendPathHelp'));
    }

    public function testAnInvalidOrReservedBackOfficePathIsRefused(): void
    {
        $this->unconfigured();

        foreach (['Admin!', 'api', 'login', 'es'] as $path) {
            $response = $this->submit(['backendPath' => $path]);

            $response->assertOK();
            $this->assertStringContainsString('id="backendPath-error"', (string) $response->getBody(), $path);
            $this->assertFalse($this->db->tableExists('modules'), $path);
        }
    }

    public function testTheChosenBackOfficePathIsInTheEnv(): void
    {
        $this->unconfigured(false);

        $response = $this->submit(['backendPath' => 'intranet']);

        $response->assertOK();
        $response->assertSee('app.backendPath = intranet');
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

    private function jobs(bool $rootWritable = true): InstallJobs
    {
        return new InstallJobs($this->installState($rootWritable));
    }

    /**
     * The JSON of `progress/install/<id>`.
     *
     * @return array<string, mixed>
     */
    private function progressOf(string $id, int $status = 200): array
    {
        $response = $this->get('progress/install/' . $id);
        $response->assertStatus($status);
        $this->assertStringContainsString('no-store', $response->response()->getHeaderLine('Cache-Control'));

        return json_decode((string) $response->getJSON(), true, 512, JSON_THROW_ON_ERROR);
    }

    public function testInvalidFormStartsNoJob(): void
    {
        $this->unconfigured();

        $this->submit(['adminEmail' => 'nope', 'progress' => '1'])->assertOK();

        $this->assertSame([], glob($this->installState()->jobsPath() . '/*') ?: []);
    }

    public function testInRequestFallbackInstallsAndReleasesTheLock(): void
    {
        $this->unconfigured(false);

        // The page's script asked for the progress page, but this server cannot answer first.
        $response = $this->submit(['progress' => '1']);

        $response->assertOK();
        $response->assertSee(lang('Install.web.doneTitle'));
        $response->assertSee('encryption.key = hex2bin:');
        $this->assertFalse($this->jobs(false)->isLocked());
        $this->assertSame([], glob($this->installState(false)->jobsPath() . '/*.json') ?: []);
    }

    public function testSecondPostWhileRunningIsRefused(): void
    {
        $this->unconfigured();
        $this->assertTrue($this->jobs()->lock(str_repeat('b', 32)));

        $response = $this->submit();

        $response->assertStatus(409);
        $response->assertSee(lang('Install.error.running'));
        $response->assertDontSee(self::PASSWORD);
        $this->assertFalse($this->db->tableExists('modules'));
        $this->assertFalse($this->installState()->isConfigured());
    }

    public function testProgressAnswersJsonWithoutSecrets(): void
    {
        $this->unconfigured();
        $jobs = $this->jobs();
        $id   = $jobs->create('es');
        $jobs->log($id, 'Install.step.migrations', ['done' => 3, 'total' => 9, 'namespace' => 'Modules\Core'], ['status' => 'running', 'step' => 'migrations', 'done' => 3, 'total' => 9, 'params' => ['namespace' => 'Modules\Core'], 'percent' => 21]);
        $jobs->log($id, 'Core.not.an.install.key');
        $jobs->putSecrets($id, ['env' => 'encryption.key = hex2bin:abc', 'demo' => ['editor' => 'demo-secret']]);

        $data = $this->progressOf($id);

        $this->assertSame(['status', 'step', 'label', 'percent', 'log', 'next'], array_keys($data));
        $this->assertSame('running', $data['status']);
        $this->assertSame(21, $data['percent']);
        $this->assertNull($data['next']);
        // In the language of the page that started the job.
        $this->assertSame('Creando las tablas (3 de 9): Modules\Core', $data['label']);
        $this->assertSame(['Creando las tablas (3 de 9): Modules\Core', ''], $data['log']);

        $json = json_encode($data, JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString('hex2bin', $json);
        $this->assertStringNotContainsString('demo-secret', $json);

        foreach (array_keys($data) as $key) {
            $this->assertDoesNotMatchRegularExpression('/pass|secret|token|key|hash|email|path|file/i', $key);
        }

        // Finished: the result page.
        $jobs->update($id, ['status' => 'done', 'step' => 'done', 'percent' => 100]);
        $this->assertStringEndsWith('install/result/' . $id, (string) $this->progressOf($id)['next']);
    }

    public function testProgressIsNotFoundForUnknownOrExpiredJobs(): void
    {
        $this->unconfigured();

        $this->assertSame(['status' => 'unknown'], $this->progressOf(str_repeat('c', 32), 404));
        $this->assertSame(['status' => 'unknown'], $this->progressOf('not-a-job', 404));

        $jobs = $this->jobs();
        $id   = $jobs->create('en');
        $file = $jobs->file($id);
        $data = json_decode((string) file_get_contents($file), true);
        $data['created_at'] -= InstallJobs::TTL + 1;
        file_put_contents($file, json_encode($data));

        $this->assertSame(['status' => 'unknown'], $this->progressOf($id, 404));
        $this->assertSame(404, $this->statusOf(fn (): TestResponse => $this->get('install/result/' . $id)));
    }

    public function testProgressIsRateLimited(): void
    {
        $this->unconfigured();
        $id      = $this->jobs()->create('en');
        $limiter = new RateLimiter();

        for ($i = 0; $i < WebInstaller::PROGRESS_LIMIT; $i++) {
            $limiter->attemptCustom('installProgress', 'ip:' . service('request')->getIPAddress(), WebInstaller::PROGRESS_LIMIT, MINUTE);
        }

        $response = $this->get('progress/install/' . $id);

        $response->assertStatus(429);
        $this->assertNotSame('', $response->response()->getHeaderLine('Retry-After'));
        $limiter->reset('installProgress', 'ip:' . service('request')->getIPAddress());
    }

    public function testResultPageShowsTheOneTimeValuesOnceAlsoOnceInstalled(): void
    {
        $this->unconfigured(false);
        $jobs = $this->jobs(false);
        $id   = $jobs->create('en');
        $jobs->putSecrets($id, ['env' => "CI_ENVIRONMENT = production\nencryption.key = hex2bin:0123\n", 'demo' => ['editor (editor@example.com)' => 'Demo-Secret-1']]);
        $jobs->update($id, ['status' => 'done', 'step' => 'done', 'percent' => 100, 'result' => [
            'envWritten' => false, 'oneTime' => true, 'demoLoaded' => ['Auth'], 'demoError' => '', 'cronLine' => '* * * * * spark schedule:run',
            'loginUrl' => 'https://intranet.example.com/login', 'username' => 'owner', 'security' => ['errors' => 2, 'warnings' => 3],
        ]]);
        // Installed meanwhile: the form is gone, the job's pages are not.
        $this->installState(false)->writeLock(['installed_at' => 'now']);
        $this->assertSame(404, $this->statusOf(fn (): TestResponse => $this->get('install')));

        $first = $this->get('install/result/' . $id);
        $first->assertOK();
        $first->assertSee(lang('Install.web.doneTitle'));
        $first->assertSee('encryption.key = hex2bin:0123');
        $first->assertSee('Demo-Secret-1');
        $first->assertSee(lang('Install.web.securityResult', [2, 3]));
        $this->assertStringContainsString('no-store', $first->response()->getHeaderLine('Cache-Control'));

        $again = $this->get('install/result/' . $id);
        $again->assertOK();
        $again->assertSee(lang('Install.web.secretsShown'));
        $again->assertDontSee('hex2bin');
        $again->assertDontSee('Demo-Secret-1');

        $this->assertSame('done', $this->progressOf($id)['status']);
    }

    public function testResultPageOfAFailedOrRunningJob(): void
    {
        $this->unconfigured();
        $jobs = $this->jobs();

        $failed = $jobs->create('en');
        $jobs->update($failed, ['status' => 'failed', 'step' => 'database', 'message' => lang('Install.error.notEmpty')]);
        $response = $this->get('install/result/' . $failed);
        $response->assertOK();
        $response->assertSee(lang('Install.web.failedTitle'));
        $response->assertSee(lang('Install.web.failedStep', [lang('Install.step.database')]));
        $response->assertSee(lang('Install.error.notEmpty'));

        $running = $jobs->create('en');
        $jobs->log($running, 'Install.step.assets', [], ['status' => 'running', 'step' => 'assets', 'percent' => 64]);
        $response = $this->get('install/result/' . $running);
        $response->assertOK();
        $this->assertStringContainsString('data-url="' . site_url('progress/install/' . $running) . '"', (string) $response->getBody());
        $this->assertStringContainsString('role="progressbar"', (string) $response->getBody());
        $this->assertStringContainsString('aria-valuenow="64"', (string) $response->getBody());
        $this->assertStringContainsString('aria-live="polite"', (string) $response->getBody());
        $response->assertSee(lang('Install.step.assets'));
    }

    public function testFormAsksForTheProgressPageOnlyThroughItsScript(): void
    {
        $this->unconfigured();

        $response = $this->get('install');

        $this->assertStringContainsString('name="progress" value="0"', (string) $response->getBody());
        $this->assertStringContainsString('data-component="core/install/progress"', (string) $response->getBody());
    }

    public function testInstallerPaths(): void
    {
        $this->assertTrue(InstallGuard::isInstallerPath('progress/install/' . str_repeat('a', 32)));
        $this->assertFalse(InstallGuard::isInstallerPath('progress/backup/' . str_repeat('a', 32)));
        $this->assertTrue(InstallGuard::isInstallerPath('install'));
        $this->assertTrue(InstallGuard::isInstallerPath('/install/'));
        $this->assertFalse(InstallGuard::isInstallerPath('installer'));
        $this->assertFalse(InstallGuard::isInstallerPath('backend/install'));
    }
}
