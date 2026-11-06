<?php

declare(strict_types=1);

namespace Modules\Core\Tests;

use CodeIgniter\Files\File;
use Modules\Core\Modules\ModuleSynchronizer;
use Modules\Core\Storage\Storage;
use Modules\Core\Storage\StorageException;
use Modules\Core\Storage\UploadRefused;
use Modules\Core\Storage\UploadRules;
use Modules\Core\Storage\UploadValidator;

/**
 * The Storage service: disks and their permissions, random names, the `stored_files` record,
 * URLs, deletion, the default rules from the settings and refused uploads in the security log.
 *
 * @internal
 */
final class StorageTest extends RbacTestCase
{
    use StorageFixturesTrait;

    private Storage $storage;

    protected function setUp(): void
    {
        parent::setUp();
        $this->storage = $this->makeStorage();
    }

    protected function tearDown(): void
    {
        $this->removeStorageRoot();
        parent::tearDown();
    }

    public function testStoresWithARandomNameAndKeepsTheOriginalOne(): void
    {
        $user = $this->createUser('ana@example.com');
        $this->actAs($user);

        $file = $this->storage->storeContents(self::pdf(), '../Informe "final".pdf', 'private', 'users/' . $user->id);

        $this->assertMatchesRegularExpression('#^users/' . $user->id . '/[a-f0-9]{32}\.pdf$#', $file->path);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{32}$/', $file->key);
        $this->assertStringNotContainsString($file->key, $file->path, 'The URL key is not the file name.');
        $this->assertSame(
            ['Informe final.pdf', 'pdf', 'application/pdf', strlen(self::pdf()), hash('sha256', self::pdf()), (int) $user->id],
            [$file->originalName, $file->extension, $file->mime, $file->size, $file->checksum, $file->uploadedBy]
        );
        $this->assertSame(self::pdf(), $this->storage->disk('private')->read($file->path));

        $found = $this->storage->find($file->key);
        $this->assertNotNull($found);
        $this->assertSame($file->path, $found->path);
        $this->assertNull($this->storage->find('../' . $file->key));
        $this->assertSame(site_url('files/private/' . $file->key), $this->storage->url($file));
        $this->assertSame(site_url('files/private/' . $file->key) . '?download=1', $this->storage->url($file, true));
    }

    public function testPrivateFilesAndFoldersAreNotWorldReadable(): void
    {
        $file = $this->storage->storeContents(self::png(), 'a.png', 'private', 'modules/news/2026');
        $disk = $this->storage->disk('private');
        $path = $disk->localPath($file->path);

        $this->assertSame(0640, fileperms($path) & 0777);
        $this->assertSame(0750, fileperms(dirname($path)) & 0777);
        $this->assertSame(0750, fileperms(dirname($path, 3)) & 0777, 'Every created folder.');
        $this->assertSame(0, fileperms($path) & 0111, 'Never executable.');

        $public = $this->storage->storeContents(self::png(), 'b.png', 'public', 'modules/news');
        $local  = $this->storage->disk('public')->localPath($public->path);
        $this->assertSame(0644, fileperms($local) & 0777);
        $this->assertSame(base_url('uploads/' . $public->path), $this->storage->url($public));
    }

    public function testDisksRefuseTraversal(): void
    {
        $disk = $this->storage->disk('private');

        foreach (['../escape.txt', 'a/../../escape.txt', '/tmp/escape.txt', '.htaccess'] as $path) {
            try {
                $disk->write($path, 'x');
                $this->fail($path);
            } catch (StorageException) {
                $this->assertFileDoesNotExist(dirname($disk->localPath('a')) . '/escape.txt');
            }
        }

        $this->expectException(StorageException::class);
        $this->storage->storeContents(self::png(), 'a.png', 'private', '../../public');
    }

    public function testPrivateDisksHaveNoPublicUrl(): void
    {
        $this->expectException(StorageException::class);
        $this->storage->disk('private')->url('a.png');
    }

    public function testUnknownDisk(): void
    {
        $this->expectException(StorageException::class);
        $this->storage->disk('s3');
    }

    public function testDeleteRemovesTheFileAndItsRecord(): void
    {
        $file = $this->storage->storeContents('hello', 'a.txt', 'private', 'tmp');
        $this->assertTrue($this->storage->disk('private')->exists($file->path));

        $this->storage->delete($file);

        $this->assertFalse($this->storage->disk('private')->exists($file->path));
        $this->assertNull($this->storage->findById($file->id));
    }

    public function testRefusedUploadsAreLoggedAndNothingIsWritten(): void
    {
        $user = $this->createUser('ana@example.com');
        $this->actAs($user);

        try {
            $this->storage->storeContents('<svg xmlns="http://www.w3.org/2000/svg"/>', 'logo.svg', 'public', 'modules/news');
            $this->fail('SVG accepted.');
        } catch (UploadRefused $e) {
            $this->assertSame('svg', $e->reason());
        }

        $this->seeInDatabase('activity_log', [
            'type' => 'security', 'module' => 'Core', 'action' => 'upload_refused', 'summary' => 'logo.svg', 'user_id' => $user->id,
        ]);
        $this->seeNumRecords(0, 'stored_files', []);
        $this->assertSame([], $this->storage->disk('public')->list('', true));
    }

    public function testFilesAlreadyOnTheServerGoThroughTheSameRules(): void
    {
        $path = $this->tempFile('<?php echo 1;');

        try {
            $this->expectException(UploadRefused::class);
            $this->storage->store(new File($path), 'private', 'tmp', UploadRules::make('txt'), 'notes.txt');
        } finally {
            unlink($path);
        }
    }

    public function testTheDefaultRulesComeFromTheSettings(): void
    {
        (new ModuleSynchronizer())->sync(); // the manifest defaults of the settings
        $validator = UploadValidator::fromSettings($this->storageConfig());
        $rules     = new UploadRules();

        $this->assertContains('pdf', $validator->allowedTypes($rules));
        $this->assertContains('jpg', $validator->allowedTypes($rules));
        $this->assertNotContains('svg', $validator->allowedTypes($rules));
        $this->assertSame(10 * 1024 * 1024, $validator->maxSize($rules));

        service('settings')->set('Core.uploadTypes', 'png, svg, php');
        service('settings')->set('Core.uploadAllowSvg', true);
        $validator = UploadValidator::fromSettings($this->storageConfig());
        $this->assertSame(['png', 'svg'], $validator->allowedTypes($rules), 'Forbidden types never pass.');
    }
}
