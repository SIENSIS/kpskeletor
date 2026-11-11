<?php

declare(strict_types=1);

namespace Modules\Files\Tests;

use CodeIgniter\Events\Events;
use CodeIgniter\Shield\Entities\User;
use Modules\Core\Storage\FileAccessRule;
use Modules\Core\Storage\StoredFile;
use Modules\Files\Policies\FileAccessPolicy;
use Modules\Files\Policies\PolicyRegistry;
use Modules\Files\Services\FolderAccessRule;
use Modules\Files\Services\Trash;

/**
 * `files/private/{key}` with the Files module: the folder ACL's `view` right decides (404
 * otherwise), trashed files only for who may delete them, a module's FileAccessPolicy decides
 * for its folder (guests included), and files outside every managed folder keep the Core's rule
 * (uploader and superadmins).
 *
 * @internal
 */
final class PrivateFileRuleTest extends FilesTestCase
{
    public function testTheRegistrarReplacesTheCoreRule(): void
    {
        $this->assertSame(FolderAccessRule::class, config('Storage')->accessRule);
        $this->assertTrue(is_a(config('Storage')->accessRule, FileAccessRule::class, true));
    }

    public function testTheFolderViewRightDecides(): void
    {
        $owner = $this->createUser('ed@example.com', 'editor');
        $owner->addPermission('files.private');
        $this->folder('private', 'shared/Docs', [['group', 'editor', 'view,upload']]);
        $id   = $this->upload($owner, 'private', 'shared/Docs', self::png(), 'foto.png');
        $key  = $this->keyOf($id);
        $path = 'files/private/' . $key;

        $this->createUser('joan@example.com', 'editor');
        $this->login('joan@example.com');
        $this->send('GET', $path)->assertStatus(200);

        $this->createUser('ana@example.com', 'user');
        $this->login('ana@example.com');
        $this->assertSame(404, $this->statusOf('GET', $path), 'No view right: 404, not 403.');

        $this->send('POST', 'logout');
        $_SESSION = [];
        $this->assertSame(404, $this->statusOf('GET', $path), 'Guests never see private files of a folder.');
    }

    public function testTrashedFilesAreOnlyForThoseWhoMayDeleteThem(): void
    {
        $owner = $this->createUser('ed@example.com', 'editor');
        $this->folder('private', 'shared/Docs', [['group', 'editor', 'view,upload'], ['group', 'admin', 'view,delete']]);
        $id = $this->upload($owner, 'private', 'shared/Docs', 'text', 'a.txt');
        (new Trash())->trash($id, null);
        $file = service('storage')->find($this->keyOf($id));
        $this->assertNotNull($file);
        $rule = new FolderAccessRule();

        $this->assertFalse($rule->canView($owner, $file));
        $this->assertTrue($rule->canView($this->createUser('boss@example.com', 'admin'), $file));
    }

    public function testFilesOutsideManagedFoldersKeepTheOwnerRule(): void
    {
        $owner = $this->createUser('ed@example.com', 'editor');
        $this->actingAs($owner);
        $file = service('storage')->storeContents('Some plain text.', 'loose.txt', 'private', 'exports');
        $rule = new FolderAccessRule();

        $this->assertTrue($rule->canView($owner, $file));
        $this->assertFalse($rule->canView($this->createUser('joan@example.com', 'editor'), $file));
        $this->assertTrue($rule->canView($this->createUser('root@example.com', 'superadmin'), $file));
        $this->assertFalse($rule->canView(null, $file));
    }

    public function testAModulePolicyDecidesForItsFolder(): void
    {
        Events::on(PolicyRegistry::EVENT, static function (PolicyRegistry $registry): void {
            $registry->register(new class () implements FileAccessPolicy {
                public function folder(): string
                {
                    return 'modules/events/attachments';
                }

                public function browse(): string
                {
                    return 'hidden';
                }

                public function canView(?User $user, StoredFile $file): bool
                {
                    return str_starts_with($file->originalName, 'public-');
                }
            });

            // Never outside modules/.
            $registry->register(new class () implements FileAccessPolicy {
                public function folder(): string
                {
                    return 'shared';
                }

                public function browse(): string
                {
                    return 'readonly';
                }

                public function canView(?User $user, StoredFile $file): bool
                {
                    return true;
                }
            });
        });

        $open   = service('storage')->storeContents('Some plain text.', 'public-agenda.txt', 'private', 'modules/events/attachments/7');
        $closed = service('storage')->storeContents('Some plain text.', 'board.txt', 'private', 'modules/events/attachments/8');
        $shared = service('storage')->storeContents('Some plain text.', 'shared.txt', 'private', 'shared');
        $rule   = new FolderAccessRule();

        $this->assertTrue($rule->canView(null, $open), 'The policy may allow guests (a public event).');
        $this->assertFalse($rule->canView($this->createUser('boss@example.com', 'admin'), $closed), 'files.all does not override a policy.');
        $this->assertTrue($rule->canView($this->createUser('root@example.com', 'superadmin'), $closed));
        $this->assertSame(['modules/events/attachments'], array_keys(PolicyRegistry::instance()->all()));
        $this->assertFalse($rule->canView(null, $shared));
    }

    private function keyOf(int $fileId): string
    {
        $row = db_connect()->table('files')->select('stored_files.file_key')->join('stored_files', 'stored_files.id = files.stored_file_id')->where('files.id', $fileId)->get()->getRowArray();
        $this->assertIsArray($row);

        return (string) $row['file_key'];
    }

    private function actingAs(User $user): void
    {
        auth()->getAuthenticator()->login($user);
    }
}
