<?php

declare(strict_types=1);

namespace Modules\Updates\Tests;

use CodeIgniter\Test\StreamFilterTrait;
use Modules\Core\Http\OutboundResponse;
use Modules\Core\Maintenance\MaintenanceMode;
use Modules\Updates\Commands\AppUpdate;
use Modules\Updates\Services\CommandRunner;
use Modules\Updates\Services\Release;
use Modules\Updates\Services\UpdateException;
use Modules\Updates\Services\Updater;
use ZipArchive;

/**
 * `php spark app:update`: the dry-run plan, releases that do not verify are refused before
 * anything changes (another key, a modified archive, no signature, no key, an unsigned git tag),
 * a verified archive is applied (protected paths untouched, replaced files kept, maintenance
 * mode and backup, composer and modules:sync in new processes), local changes block the next
 * update, and a failure keeps maintenance mode on and names the backup.
 *
 * @internal
 */
final class AppUpdateTest extends UpdatesTestCase
{
    use StreamFilterTrait;

    private const DOWNLOAD = 'https://github.com/acme/app/releases/download/v0.2.0/';

    private TestSigner $signer;

    private FakeRunner $runner;

    /** @var list<string> passwords the backup was made with */
    private array $backups = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->signer             = new TestSigner();
        $this->config->publicKeys = $this->signer->publicKey();
        $this->runner             = new FakeRunner();

        // The installed application.
        $app = $this->config->rootPath;
        mkdir($app . '/modules/Core', 0755, true);
        mkdir($app . '/writable', 0755, true);
        file_put_contents($app . '/spark', 'old spark');
        file_put_contents($app . '/README.md', 'same');
        file_put_contents($app . '/.env', 'encryption.key = keep-me');
        file_put_contents($app . '/writable/data.txt', 'keep');

        putenv('BACKUP_PASSWORD=correct-horse-battery');
    }

    protected function tearDown(): void
    {
        AppUpdate::$updater = null;
        putenv('BACKUP_PASSWORD');
        (new MaintenanceMode())->disable();
        parent::tearDown();
    }

    public function testTheDryRunShowsThePlanAndChangesNothing(): void
    {
        $this->publishArchive($this->archive());
        AppUpdate::$updater = $this->updater();

        command('app:update --dry-run');
        $output = $this->getStreamFilterBuffer();

        $this->assertStringContainsString(lang('Updates.cli.available', ['0.1.0', '0.2.0', 'archive']), $output);
        $this->assertStringContainsString(lang('Updates.plan.download', ['app-0.2.0.zip']), $output);
        $this->assertStringContainsString(lang('Updates.plan.verify'), $output);
        $this->assertStringContainsString(lang('Updates.plan.backup'), $output);
        $this->assertStringContainsString(lang('Updates.plan.composer'), $output);
        $this->assertStringContainsString(lang('Updates.cli.noFileList'), $output);
        $this->assertStringContainsString(lang('Updates.cli.dryRunOk'), $output);

        $this->assertSame('old spark', file_get_contents($this->config->rootPath . '/spark'));
        $this->assertFalse((new MaintenanceMode())->isOn());
        $this->assertSame([], $this->runner->commands);
        $this->assertSame([], $this->backups);
        $this->assertSame([self::RELEASES], array_column($this->transport->sent, 'url'), 'Only the release list is read.');

        // Without a public key the plan says it would be refused.
        $this->config->publicKeys = '';
        command('app:update --dry-run');
        $this->assertStringContainsString(lang('Updates.error.noKey'), $this->getStreamFilterBuffer());
        $this->assertStringContainsString(lang('Updates.cli.dryRunBlocked'), $this->getStreamFilterBuffer());
    }

    public function testUpToDate(): void
    {
        $this->publish([self::release('v0.1.0'), self::release('v0.0.9')]);
        AppUpdate::$updater = $this->updater();

        command('app:update --dry-run');
        $this->assertStringContainsString(lang('Updates.cli.upToDate', ['0.1.0']), $this->getStreamFilterBuffer());
    }

    public function testReleasesThatDoNotVerifyAreRefusedBeforeAnythingChanges(): void
    {
        $archive = $this->archive();

        // Signed with another key.
        $this->publishArchive($archive, (new TestSigner())->sign($archive));
        $this->assertRefusedUntouched(explode('(', lang('Updates.error.unknownKey', ['']))[0]);

        // The archive changed after it was signed (and its checksum with it).
        $this->publishArchive($archive . 'x', $this->signer->sign($archive), hash('sha256', $archive . 'x'));
        $this->assertRefusedUntouched(lang('Updates.error.signature'));

        // The checksum does not match.
        $this->publishArchive($archive, null, str_repeat('0', 64));
        $this->assertRefusedUntouched(lang('Updates.error.checksum'));

        // No signature published, or no key configured.
        $this->publish([self::release('v0.2.0', '', ['app-0.2.0.zip' => self::DOWNLOAD . 'app-0.2.0.zip'])]);
        $this->assertRefusedUntouched(lang('Updates.error.unsigned', ['v0.2.0']));
        $this->publishArchive($archive);
        $this->config->publicKeys = '';
        $this->assertRefusedUntouched(lang('Updates.error.noKey'));

        $this->assertNotEmpty(db_connect()->table('activity_log')->where('action', 'release_refused')->where('type', 'security')->get()->getResultArray());
    }

    public function testAVerifiedArchiveIsApplied(): void
    {
        $this->publishArchive($this->archive());
        AppUpdate::$updater = $this->updater();

        command('app:update --yes');
        $output = $this->getStreamFilterBuffer();
        $app    = $this->config->rootPath;

        $this->assertStringContainsString(lang('Updates.step.verified'), $output);
        $this->assertStringContainsString(lang('Updates.step.done', ['0.2.0']), $output);
        $this->assertSame('new spark', file_get_contents($app . '/spark'));
        $this->assertSame('<?php // 0.2.0', file_get_contents($app . '/modules/Core/Config/Version.php'));
        $this->assertSame('encryption.key = keep-me', file_get_contents($app . '/.env'), '.env is never written.');
        $this->assertSame('keep', file_get_contents($app . '/writable/data.txt'), 'writable/ is never written.');
        $this->assertFileDoesNotExist($app . '/writable/evil.php');
        $this->assertSame('old spark', file_get_contents($this->config->path . '/previous/0.1.0/spark'), 'Replaced files are kept.');
        $this->assertFileDoesNotExist($this->config->path . '/previous/0.1.0/README.md', 'Unchanged files are not copied.');
        $this->assertSame(['correct-horse-battery'], $this->backups);
        $this->assertSame([
            'composer install --no-dev --no-interaction --no-progress --optimize-autoloader',
            PHP_BINARY . ' spark modules:sync',
            PHP_BINARY . ' spark app:cache-clear',
        ], $this->runner->commands);
        $this->assertSame(['maintenance on', 'backup', 'composer'], array_slice($this->runner->events, 0, 3), 'Maintenance mode and the backup come first.');
        $this->assertFalse((new MaintenanceMode())->isOn());
        $this->assertSame(10485760 * 10, $this->transport->sent[1]['maxBytes'], 'The archive uses the archive size limit.');
        $this->assertCount(1, db_connect()->table('activity_log')->where('action', 'updated')->where('module', 'Updates')->get()->getResultArray());
        $this->assertSame([], glob($this->config->path . '/staging-*'), 'The staging folder is removed.');

        // A file changed by hand afterwards blocks the next update.
        $updater = $this->updater();
        $this->assertSame([], $updater->localChanges());
        file_put_contents($app . '/spark', 'edited');
        $this->assertSame(['spark'], $updater->localChanges());
        $this->assertStringContainsString('spark', implode(' ', $updater->problems(new Release('0.3.0', 'v0.3.0', assets: ['a.zip' => 'x', 'a.zip.minisig' => 'x', 'a.zip.sha256' => 'x']))));
    }

    public function testAFailureKeepsMaintenanceModeOnAndNamesTheBackup(): void
    {
        $this->publishArchive($this->archive());
        $this->runner->fail = 'composer';
        $updater            = $this->updater();
        $release            = $updater->available();
        $this->assertNotNull($release);

        try {
            $updater->update($release, 'correct-horse-battery');
            $this->fail('The update should fail.');
        } catch (UpdateException $e) {
            $this->assertStringContainsString('backup-update.kpb', $e->getMessage());
            $this->assertStringContainsString('previous/0.1.0', $e->getMessage());
        }

        $this->assertTrue((new MaintenanceMode())->isOn());
        $state = (new MaintenanceMode())->state();
        $this->assertNotNull($state);
        $this->assertSame(Updater::MAINTENANCE_REASON, $state['reason']);
        $this->assertFalse($state['bypass'], 'No superadmin bypass during an update.');
        $this->assertNotEmpty(db_connect()->table('activity_log')->where('action', 'update_failed')->where('type', 'security')->get()->getResultArray());

        // A second run refuses while maintenance mode is on.
        $this->assertContains(lang('Updates.error.maintenanceOn'), $updater->problems($release));
    }

    public function testGitInstallsVerifyTheTag(): void
    {
        $this->config->method = 'git';
        $this->publish([self::release('v0.2.0')]);
        $updater = $this->updater();
        $release = $updater->available();
        $this->assertNotNull($release);
        $this->assertContains(lang('Updates.plan.gitVerify', ['v0.2.0']), $updater->plan($release));
        $this->assertSame([], $updater->problems($release), 'Git installs need no archive nor minisign key.');

        $this->runner->fail = 'verify-tag';

        try {
            $updater->update($release, 'correct-horse-battery');
            $this->fail('An unsigned tag is refused.');
        } catch (UpdateException $e) {
            $this->assertSame(lang('Updates.error.tagNotVerified', ['v0.2.0']), $e->getMessage());
        }

        $this->assertFalse((new MaintenanceMode())->isOn());
        $this->assertSame([], $this->backups);

        $this->runner->fail     = null;
        $this->runner->commands = [];
        $updater->update($release, 'correct-horse-battery');
        $this->assertContains('git merge --ff-only -- refs/tags/v0.2.0', $this->runner->commands);
        $this->assertContains('git status --porcelain --untracked-files=no', $this->runner->commands);

        $this->runner->status = " M app/Config/App.php\n";
        $this->assertSame(['app/Config/App.php'], $updater->localChanges());
    }

    private function assertRefusedUntouched(string $message): void
    {
        $updater = $this->updater();
        $release = $updater->available();
        $this->assertNotNull($release);

        try {
            $updater->update($release, 'correct-horse-battery');
            $this->fail('The release should be refused.');
        } catch (UpdateException $e) {
            $this->assertStringStartsWith($message, $e->getMessage());
        }

        $this->assertSame('old spark', file_get_contents($this->config->rootPath . '/spark'));
        $this->assertFalse((new MaintenanceMode())->isOn());
        $this->assertSame([], $this->backups);
        $this->assertSame([], $this->runner->commands);
        $this->assertSame([], glob($this->config->path . '/staging-*'));
        $this->config->publicKeys = $this->signer->publicKey();
    }

    private function updater(): Updater
    {
        $maintenance = new MaintenanceMode();
        $events      = &$this->runner->events;

        return new Updater(
            $this->config,
            $this->source(),
            $this->runner,
            $maintenance,
            function (string $password) use ($maintenance, &$events): string {
                $events[]        = $maintenance->isOn() ? 'maintenance on' : 'maintenance off';
                $events[]        = 'backup';
                $this->backups[] = $password;

                return 'backup-update.kpb';
            },
            static function (string $line, ?string $color): void {
                \CodeIgniter\CLI\CLI::write($line, $color);
            },
            $this->client(10485760 * 10),
        );
    }

    /**
     * Publishes v0.2.0 with its archive, signature and checksum.
     */
    private function publishArchive(string $archive, ?string $signature = null, ?string $checksum = null): void
    {
        $this->publish([self::release('v0.1.0'), self::release('v0.2.0', 'Notes', [
            'app-0.2.0.zip'         => self::DOWNLOAD . 'app-0.2.0.zip',
            'app-0.2.0.zip.minisig' => self::DOWNLOAD . 'app-0.2.0.zip.minisig',
            'app-0.2.0.zip.sha256'  => self::DOWNLOAD . 'app-0.2.0.zip.sha256',
        ])]);

        $this->transport->routes[self::DOWNLOAD . 'app-0.2.0.zip']         = new OutboundResponse(200, [], $archive);
        $this->transport->routes[self::DOWNLOAD . 'app-0.2.0.zip.minisig'] = new OutboundResponse(200, [], $signature ?? $this->signer->sign($archive));
        $this->transport->routes[self::DOWNLOAD . 'app-0.2.0.zip.sha256']  = new OutboundResponse(200, [], ($checksum ?? hash('sha256', $archive)) . '  app-0.2.0.zip');
    }

    /**
     * The contents of a release archive (one top-level folder).
     */
    private function archive(): string
    {
        $file = $this->root . '/build-' . bin2hex(random_bytes(4)) . '.zip';
        $zip  = new ZipArchive();
        $zip->open($file, ZipArchive::CREATE);
        $zip->addFromString('app-0.2.0/spark', 'new spark');
        $zip->addFromString('app-0.2.0/README.md', 'same');
        $zip->addFromString('app-0.2.0/modules/Core/Config/Version.php', '<?php // 0.2.0');
        $zip->addFromString('app-0.2.0/.env', 'encryption.key = from-the-archive');
        $zip->addFromString('app-0.2.0/writable/evil.php', '<?php');
        $zip->close();
        $contents = (string) file_get_contents($file);
        unlink($file);

        return $contents;
    }
}

/**
 * Records the commands `app:update` runs; fails the one whose arguments contain `$fail`.
 */
final class FakeRunner implements CommandRunner
{
    /** @var list<string> */
    public array $commands = [];

    /** @var list<string> */
    public array $events = [];

    public ?string $fail = null;

    public string $status = '';

    public function run(array $command, string $cwd): array
    {
        $line             = implode(' ', $command);
        $this->commands[] = $line;
        $this->events[]   = explode(' ', basename($command[0]))[0];

        if ($this->fail !== null && str_contains($line, $this->fail)) {
            return ['code' => 1, 'output' => $this->fail . ' failed'];
        }

        return ['code' => 0, 'output' => str_contains($line, 'status --porcelain') ? $this->status : ''];
    }
}
