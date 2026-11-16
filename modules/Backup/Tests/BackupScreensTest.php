<?php

declare(strict_types=1);

namespace Modules\Backup\Tests;

use CodeIgniter\I18n\Time;
use CodeIgniter\Test\StreamFilterTrait;
use Kpcrudy\Crud;
use Modules\Backup\Services\JobStore;

/**
 * The back office (`backend/backups`): permissions of every screen and forged POSTs, creating
 * and verifying, downloading and restoring with re-authentication, the typed confirmation, the
 * restore job with its progress endpoint and the merged `.env` shown once, and deleting.
 *
 * @internal
 */
final class BackupScreensTest extends BackupTestCase
{
    use StreamFilterTrait;

    protected function tearDown(): void
    {
        Time::setTestNow();
        parent::tearDown();
    }

    public function testTheScreensNeedTheirPermissions(): void
    {
        $file = $this->service()->create(self::PASSWORD)['file'];
        $code = $this->code($file);

        $this->loginWith();
        $this->send('GET', 'backend/backups')->assertStatus(403);
        $this->send('GET', 'backend/backups/create')->assertStatus(403);
        $this->send('POST', 'backend/backups/create', $this->createForm())->assertStatus(403);
        $this->send('POST', "backend/backups/verify/{$code}", ['password' => self::PASSWORD])->assertStatus(403);
        $this->send('GET', "backend/backups/download/{$code}")->assertStatus(403);
        $this->send('POST', "backend/backups/restore/{$code}", $this->restoreForm($file))->assertStatus(403);
        $this->send('POST', 'backend/backups/upload')->assertStatus(403);
        $this->assertCount(1, $this->service()->repository()->files());

        // Administrators list and create, but never download, restore or delete (default grants).
        $_SESSION      = [];
        $this->cookies = [];
        $this->createUser('admin@example.com', 'admin');
        $this->login('admin@example.com');
        $this->send('GET', 'backend/backups')->assertOK();
        $list = $this->body('GET', 'backend/backups');
        $this->assertStringContainsString('backend/backups/create', $list);
        $this->assertStringNotContainsString('backend/backups/download/', $list);
        $this->assertStringNotContainsString('backend/backups/restore/', $list);
        $this->send('GET', "backend/backups/download/{$code}")->assertStatus(403);
        $this->send('GET', "backend/backups/restore/{$code}")->assertStatus(403);
        $this->send('POST', "backend/backups/restore/{$code}", $this->restoreForm($file))->assertStatus(403);
        $this->send('POST', "backend/backups/delete/{$code}");
        $this->assertFileExists($this->backupPath($file), 'Administrators cannot delete backups.');
    }

    public function testCreateAndVerifyFromTheBackOffice(): void
    {
        $this->createUser('admin@example.com', 'admin');
        $this->login('admin@example.com');

        $this->assertStringContainsString(lang('Backup.error.passwordShort', [12]), $this->body('POST', 'backend/backups/create', $this->createForm('short')));
        $this->assertStringContainsString(lang('Backup.error.passwordMismatch'), $this->body('POST', 'backend/backups/create', $this->createForm(self::PASSWORD, 'another-password')));
        $this->assertCount(0, $this->service()->repository()->files());

        $this->send('POST', 'backend/backups/create', $this->createForm())->assertRedirectTo(site_url('backend/backups'));
        $files = $this->service()->repository()->files();
        $this->assertCount(1, $files);
        $this->assertSame('ui', $this->service()->repository()->findByFile($files[0])['source'] ?? null);

        $code = $this->code($files[0]);
        $this->assertStringContainsString(lang('Backup.error.password'), $this->body('POST', "backend/backups/verify/{$code}", ['password' => 'wrong-password-123']));
        $this->assertStringContainsString(lang('Backup.msg.verified'), $this->body('POST', "backend/backups/verify/{$code}", ['password' => self::PASSWORD]));
        $this->assertNotFound('GET', 'backend/backups/verify/not-a-code');
    }

    public function testDownloadNeedsReauthenticationAndIsLogged(): void
    {
        $file = $this->service()->create(self::PASSWORD)['file'];
        $code = $this->code($file);
        $this->createUser('root@example.com', 'superadmin');
        $this->login('root@example.com');

        $response = $this->send('GET', "backend/backups/download/{$code}");
        $response->assertStatus(200);
        $this->assertInstanceOf(\CodeIgniter\HTTP\DownloadResponse::class, $response->response());
        $this->assertStringStartsWith('application/octet-stream', $response->response()->getHeaderLine('Content-Type'));
        $this->assertCount(1, $this->securityEntries('download'));

        Time::setTestNow(Time::now()->addMinutes(16));
        $this->send('GET', "backend/backups/download/{$code}")->assertRedirectTo(site_url('auth/reauth'));
        $this->send('GET', "backend/backups/restore/{$code}")->assertRedirectTo(site_url('auth/reauth'));
        $this->send('POST', "backend/backups/restore/{$code}", $this->restoreForm($file))->assertRedirectTo(site_url('auth/reauth'));
        $this->assertCount(1, $this->securityEntries('download'));
    }

    public function testRestoreFromTheBackOffice(): void
    {
        $root = $this->createUser('root@example.com', 'superadmin');
        $file = $this->service()->create(self::PASSWORD, 'full', 'console')['file'];
        $code = $this->code($file);
        $this->createUser('later@example.com');
        // This server's .env has another key: the merged content is shown once.
        file_put_contents($this->root . '/.env', "app.baseURL = 'https://here.example.com/'\nencryption.key = hex2bin:" . str_repeat('dd', 32) . "\n");
        $this->login('root@example.com');

        $this->assertStringContainsString(lang('Backup.form.typeConfirm', [$file]), $this->body('GET', "backend/backups/restore/{$code}"));
        $this->assertStringContainsString(lang('Backup.error.confirm'), $this->body('POST', "backend/backups/restore/{$code}", $this->restoreForm('wrong-name')));
        $this->assertStringContainsString(lang('Backup.error.password'), $this->body('POST', "backend/backups/restore/{$code}", $this->restoreForm($file, 'wrong-password-123')));
        $this->assertCount(1, $this->securityEntries('restore_refused'));
        $this->assertSame(1, db_connect()->table('users')->where('username', 'later')->countAllResults(), 'Nothing restored yet.');

        $page = $this->body('POST', "backend/backups/restore/{$code}", $this->restoreForm($file));

        $this->assertSame(0, db_connect()->table('users')->where('username', 'later')->countAllResults(), 'Restored.');
        $this->assertStringContainsString(lang('Backup.msg.restored'), $page);
        $this->assertStringContainsString('encryption.key = hex2bin:' . str_repeat('aa', 32), $page, 'The merged .env is shown to copy.');
        $this->assertStringContainsString("app.baseURL = 'https://here.example.com/'", $page);
        $this->assertSame("app.baseURL = 'https://here.example.com/'\nencryption.key = hex2bin:" . str_repeat('dd', 32) . "\n", file_get_contents($this->root . '/.env'), 'The back office never writes the .env.');

        $jobs = glob($this->config->path . '/jobs/*.json') ?: [];
        $this->assertCount(1, $jobs);
        $job = basename($jobs[0], '.json');

        // Once: the second visit no longer shows the secrets.
        $again = $this->body('GET', "backend/backups/jobs/{$job}");
        $this->assertStringNotContainsString(str_repeat('aa', 32), $again);
        $this->assertStringContainsString(lang('Backup.msg.envShown'), $again);

        // The progress endpoint: status only, no secrets, unknown ids 404.
        $response = $this->send('GET', "progress/backup/{$job}");
        $response->assertOK();
        $progress = json_decode((string) $response->response()->getBody(), true);
        $this->assertSame(['status' => 'done', 'step' => 'done', 'label' => lang('Backup.step.done'), 'percent' => 100], $progress);
        $this->send('GET', 'progress/backup/' . str_repeat('0', 32))->assertStatus(404);
        $this->send('GET', 'progress/backup/not-a-job')->assertStatus(404);

        // Another user cannot open someone else's job.
        $other = (new JobStore($this->config))->create('restore', (int) $root->id + 1000);
        $this->assertNotFound('GET', "backend/backups/jobs/{$other}");
    }

    public function testABackupCreatedInTheBackOfficeRestoresFromTheConsole(): void
    {
        $this->createUser('root@example.com', 'superadmin');
        $this->login('root@example.com');
        file_put_contents($this->root . '/public/photo.jpg', 'jpeg');
        $this->send('POST', 'backend/backups/create', $this->createForm())->assertRedirectTo(site_url('backend/backups'));
        $file = $this->service()->repository()->files()[0] ?? '';

        $this->createUser('later@example.com');
        unlink($this->root . '/public/photo.jpg');
        putenv('BACKUP_PASSWORD=' . self::PASSWORD);

        try {
            command("backup:restore {$file} --yes");
        } finally {
            putenv('BACKUP_PASSWORD');
        }

        $this->assertSame(0, db_connect()->table('users')->where('username', 'later')->countAllResults());
        $this->assertSame('jpeg', file_get_contents($this->root . '/public/photo.jpg'));
    }

    public function testDeletingABackupDeletesItsFile(): void
    {
        $file = $this->service()->create(self::PASSWORD)['file'];
        $this->createUser('root@example.com', 'superadmin');
        $this->login('root@example.com');

        $this->send('POST', 'backend/backups/delete/' . $this->code($file));

        $this->assertFileDoesNotExist($this->backupPath($file));
        $this->assertNull($this->service()->repository()->findByFile($file));
    }

    public function testFilesFoundInTheFolderAreListedAndOthersIgnored(): void
    {
        $file = $this->service()->create(self::PASSWORD)['file'];
        db_connect()->table('backups')->emptyTable();
        file_put_contents($this->config->path . '/notes.txt', 'x');
        $this->createUser('root@example.com', 'superadmin');
        $this->login('root@example.com');

        $list = $this->body('GET', 'backend/backups');

        $this->assertStringContainsString(lang('Backup.source.found'), $list);
        $this->assertSame('found', $this->service()->repository()->findByFile($file)['source'] ?? null);
        $this->assertStringNotContainsString('notes.txt', $list);
    }

    private function code(string $file): string
    {
        $row = $this->service()->repository()->findByFile($file);
        $this->assertIsArray($row);

        return Crud::maskId((int) $row['id'], 'backups');
    }

    /**
     * @return array<string, string>
     */
    private function createForm(string $password = self::PASSWORD, ?string $confirm = null): array
    {
        return ['type' => 'full', 'password' => $password, 'password_confirm' => $confirm ?? $password];
    }

    /**
     * @return array<string, string>
     */
    private function restoreForm(string $confirm, string $password = self::PASSWORD): array
    {
        return ['password' => $password, 'only' => 'all', 'confirm' => $confirm];
    }

    /**
     * @param array<string, mixed> $params
     */
    private function body(string $method, string $path, array $params = []): string
    {
        return html_entity_decode((string) $this->send($method, $path, $params)->response()->getBody(), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}
