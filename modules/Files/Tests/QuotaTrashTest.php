<?php

declare(strict_types=1);

namespace Modules\Files\Tests;

use Modules\Files\Services\QuotaService;
use Modules\Files\Services\Spaces;
use Modules\Files\Services\Trash;
use RuntimeException;

/**
 * Quotas (folder and personal space) and the trash: trashed files keep counting until they are
 * purged; purging deletes the stored file and its thumbnail; restoring brings the file back.
 *
 * @internal
 */
final class QuotaTrashTest extends FilesTestCase
{
    public function testAFolderQuotaLimitsEverythingBelowIt(): void
    {
        $editor = $this->createUser('ed@example.com', 'editor');
        $editor->addPermission('files.private');
        $this->folder('private', 'shared/Small', [['group', 'editor', 'view,upload,delete']], 1000);
        $this->folder('private', 'shared/Small/Sub');

        $this->upload($editor, 'private', 'shared/Small', str_repeat('a', 600), 'a.txt');

        try {
            $this->upload($editor, 'private', 'shared/Small/Sub', str_repeat('b', 600), 'b.txt');
            $this->fail('The parent quota applies to subfolders.');
        } catch (RuntimeException $e) {
            $this->assertSame(lang('Files.errors.quota'), $e->getMessage());
        }

        $this->assertSame(1, db_connect()->table('files')->countAllResults());
        $this->assertSame(1, db_connect()->table('stored_files')->countAllResults(), 'Nothing is stored when the quota refuses.');
        $this->upload($editor, 'private', 'shared/Small/Sub', str_repeat('c', 300), 'c.txt');
        $this->assertSame(900, (new QuotaService())->used('private', 'shared/Small'));
    }

    public function testTrashedFilesCountUntilPurged(): void
    {
        $editor = $this->createUser('ed@example.com', 'editor');
        $editor->addPermission('files.private');
        $this->folder('private', 'shared/Box', [['group', 'editor', 'view,upload,delete']], 1000);
        $id = $this->upload($editor, 'private', 'shared/Box', str_repeat('a', 800), 'a.txt');

        (new Trash())->trash($id, (int) $editor->id);
        $this->assertFalse((new QuotaService())->allows('private', 'shared/Box', 300), 'The trash still counts.');

        (new Trash())->restore($id);
        $this->seeInDatabase('files', ['id' => $id, 'deleted_at' => null]);

        (new Trash())->trash($id, (int) $editor->id);
        $stored = db_connect()->table('files')->where('id', $id)->get()->getRowArray();
        $this->assertIsArray($stored);
        $file = service('storage')->findById((int) $stored['stored_file_id']);
        $this->assertNotNull($file);
        $this->assertTrue(service('storage')->disk('private')->exists($file->path));

        (new Trash())->purge($id);
        $this->dontSeeInDatabase('files', ['id' => $id]);
        $this->dontSeeInDatabase('stored_files', ['id' => $file->id]);
        $this->assertFalse(service('storage')->disk('private')->exists($file->path), 'The file is gone from the disk.');
        $this->assertTrue((new QuotaService())->allows('private', 'shared/Box', 900));
    }

    public function testThePersonalQuotaComesFromTheSetting(): void
    {
        $editor = $this->createUser('ed@example.com', 'editor');
        $home   = Spaces::personalPath((int) $editor->id);
        $this->folder('private', $home, []);
        $this->setSetting('Files.personalQuota', 1);

        $this->assertSame([$home => 1048576], (new QuotaService())->limits('private', $home . '/Docs'));
        $this->upload($editor, 'private', $home, str_repeat('x', 1000), 'small.txt');

        $this->expectException(RuntimeException::class);
        $this->upload($editor, 'private', $home, str_repeat('y', 1048000), 'big.txt');
    }

    public function testThumbnailsAreMadeForImagesAndPurgedWithThem(): void
    {
        $editor = $this->createUser('ed@example.com', 'editor');
        $this->folder('public', 'shared/Pics', [['group', 'editor', 'view,upload,delete']]);
        $id  = $this->upload($editor, 'public', 'shared/Pics', self::png(400, 200), 'wide.png');
        $row = db_connect()->table('files')->where('id', $id)->get()->getRowArray();

        $this->assertIsArray($row);
        $this->assertIsString($row['thumbnail']);
        $this->assertStringStartsWith('modules/files/thumbnails/', $row['thumbnail']);
        $thumb = @imagecreatefromstring(service('storage')->disk('private')->read($row['thumbnail']));
        $this->assertNotFalse($thumb);
        $this->assertSame(320, imagesx($thumb));
        $this->assertSame(160, imagesy($thumb));

        (new Trash())->purge($id);
        $this->assertFalse(service('storage')->disk('private')->exists($row['thumbnail']));
    }

    public function testUploadsNeedTheUploadRightAndNeverGoToModuleFolders(): void
    {
        $user = $this->createUser('ana@example.com', 'editor');
        $this->folder('public', 'shared/ReadOnly', [['group', 'editor', 'view']]);
        $this->folder('public', 'modules/news', [['group', 'editor', 'view,upload']]);

        foreach (['shared/ReadOnly', 'modules/news', 'shared/Unknown'] as $folder) {
            try {
                $this->upload($user, 'public', $folder, 'hello', 'a.txt');
                $this->fail($folder);
            } catch (RuntimeException $e) {
                $this->assertSame(lang('Files.errors.forbidden'), $e->getMessage(), $folder);
            }
        }

        $this->assertSame(0, db_connect()->table('stored_files')->countAllResults());
    }
}
