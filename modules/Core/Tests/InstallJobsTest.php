<?php

declare(strict_types=1);

namespace Modules\Core\Tests;

use CodeIgniter\Test\CIUnitTestCase;
use Modules\Core\Install\InstallJob;
use Modules\Core\Install\InstallJobs;
use Modules\Core\Install\InstallState;

/**
 * The web installer's job files (session 39): permissions, one-time secrets, expiry and purge,
 * the installation lock and the log limit; the percentage of each step.
 *
 * @internal
 */
final class InstallJobsTest extends CIUnitTestCase
{
    private string $root = '';

    private int $now = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->now  = time(); // the lock's age is its file's modification time
        $this->root = sys_get_temp_dir() . '/kps-jobs-' . bin2hex(random_bytes(6));
        mkdir($this->root . '/writable', 0777, true);
    }

    protected function tearDown(): void
    {
        helper('filesystem');
        delete_files($this->root, true, false, true);
        @rmdir($this->root);
        parent::tearDown();
    }

    private function jobs(): InstallJobs
    {
        return new InstallJobs(new InstallState($this->root, $this->root . '/writable'), InstallJobs::TTL, fn (): int => $this->now);
    }

    private function mode(string $path): int
    {
        clearstatcache();

        return fileperms($path) & 0777;
    }

    public function testJobFilesArePrivate(): void
    {
        $jobs = $this->jobs();
        $id   = $jobs->create('ca');

        $this->assertMatchesRegularExpression(InstallJobs::ID, $id);
        $this->assertSame(0700, $this->mode($this->root . '/writable/install-jobs'));
        $this->assertSame(0600, $this->mode($jobs->file($id)));

        $jobs->putSecrets($id, ['env' => 'encryption.key = hex2bin:00', 'demo' => ['editor' => 'pw']]);
        $this->assertSame(0600, $this->mode($jobs->secretsFile($id)));

        $job = $jobs->get($id);
        $this->assertNotNull($job);
        $this->assertSame('queued', $job['status']);
        $this->assertSame('ca', $job['locale']);
        $this->assertStringNotContainsString('hex2bin', (string) file_get_contents($jobs->file($id)));
    }

    public function testSecretsAreReadOnceThenGone(): void
    {
        $jobs = $this->jobs();
        $id   = $jobs->create('en');
        $jobs->putSecrets($id, ['env' => 'X=1', 'demo' => ['editor' => 'secret-pw']]);

        $this->assertSame(['env' => 'X=1', 'demo' => ['editor' => 'secret-pw']], $jobs->takeSecrets($id));
        $this->assertNull($jobs->takeSecrets($id));
        $this->assertFileDoesNotExist($jobs->secretsFile($id));
    }

    public function testExpiredJobsAreUnreadableAndPurged(): void
    {
        $jobs = $this->jobs();
        $old  = $jobs->create('en');
        $jobs->putSecrets($old, ['env' => 'X=1']);

        $this->now += InstallJobs::TTL - 1;
        $fresh = $jobs->create('en');
        $this->assertNotNull($jobs->get($old));

        $this->now += 2;
        $this->assertNull($jobs->get($old));
        $this->assertNull($jobs->takeSecrets($old));
        $this->assertNotNull($jobs->get($fresh));

        $this->assertSame(2, $jobs->purge());
        $this->assertFileDoesNotExist($jobs->file($old));
        $this->assertFileDoesNotExist($jobs->secretsFile($old));
        $this->assertFileExists($jobs->file($fresh));
    }

    public function testUnknownOrMalformedIdsAreNull(): void
    {
        $jobs = $this->jobs();

        $this->assertNull($jobs->get(str_repeat('a', 32)));
        $this->assertNull($jobs->get('../../install.lock'));
        $this->assertNull($jobs->get(strtoupper(str_repeat('a', 32))));
    }

    public function testOnlyOneInstallationHoldsTheLock(): void
    {
        $jobs = $this->jobs();

        $this->assertTrue($jobs->lock('first'));
        $this->assertTrue($jobs->isLocked());
        $this->assertFalse($jobs->lock('second'));

        $jobs->unlock('second'); // not the holder: nothing happens
        $this->assertTrue($jobs->isLocked());

        $jobs->unlock('first');
        $this->assertFalse($jobs->isLocked());
        $this->assertTrue($jobs->lock('second'));
    }

    public function testStaleLockIsIgnored(): void
    {
        $jobs = $this->jobs();
        $this->assertTrue($jobs->lock('crashed'));
        touch($this->root . '/writable/install-jobs/running.lock', $this->now);

        $this->now += InstallJobs::TTL + 1;

        $this->assertFalse($jobs->isLocked());
        $this->assertTrue($jobs->lock('next'));
    }

    public function testLogKeepsTheLatestLines(): void
    {
        $jobs = $this->jobs();
        $id   = $jobs->create('en');

        for ($i = 0; $i < InstallJobs::MAX_LOG + 5; $i++) {
            $jobs->log($id, 'Install.step.migrations', ['done' => $i]);
        }

        $log = $jobs->get($id)['log'] ?? [];
        $this->assertCount(InstallJobs::MAX_LOG, $log);
        $this->assertSame(InstallJobs::MAX_LOG + 4, $log[InstallJobs::MAX_LOG - 1]['params']['done']);
    }

    public function testPercentagesGrowWithTheSteps(): void
    {
        $previous = -1;

        foreach (array_keys(InstallJob::RANGES) as $code) {
            $first = InstallJob::percent($code);
            $last  = InstallJob::percent($code, 3, 3);

            $this->assertGreaterThan($previous, $last, $code);
            $this->assertGreaterThanOrEqual($first, $last, $code);
            $previous = $last;
        }

        $this->assertSame(100, InstallJob::percent('done'));
        $this->assertSame(InstallJob::percent('migrations', 5, 5), InstallJob::percent('migrations', 9, 5));
        $this->assertSame(0, InstallJob::percent('unknown'));
    }
}
