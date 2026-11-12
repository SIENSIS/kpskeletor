<?php

declare(strict_types=1);

namespace Modules\Files\Tests;

use CodeIgniter\Shield\Entities\User;
use Modules\Auth\Services\Avatars;
use Modules\Core\Storage\Picker\PickerField;
use Modules\Core\Storage\StoredFile;
use Modules\Core\Tests\TestUploadedFile;
use Modules\Files\Services\FolderAcl;
use Modules\Files\Services\PickerBackend;
use Modules\Files\Services\Rights;
use RuntimeException;

/**
 * The file picker with the Files module (docs/files.md, "File picker"): the token grants the
 * upload (no files.* permission), folders are created on demand with the space's rights (the
 * avatar folder is readable by every signed-in user), quotas apply, and browsing / choosing
 * stays inside the roots and the folder ACL.
 *
 * @internal
 */
final class PickerBackendTest extends FilesTestCase
{
    private function pick(User $user, PickerField $field, string $contents, string $name): StoredFile
    {
        auth()->login($user);

        try {
            return PickerField::backend()->upload($user, $field->scope($user), TestUploadedFile::fromContents($contents, $name));
        } finally {
            auth()->logout();
        }
    }

    public function testTheFilesBackendIsConfigured(): void
    {
        $this->assertInstanceOf(PickerBackend::class, PickerField::backend());
    }

    public function testAnAvatarNeedsNoFilesPermissionAndLandsInTheAvatarFolder(): void
    {
        $ana = $this->createUser('ana@example.com', 'user');
        $this->assertFalse(auth()->user()?->can('files.upload') ?? false);
        $file = $this->pick($ana, Avatars::field(), self::png(), 'me.png');

        $folder = 'users/' . $ana->id . '/avatar';
        $this->assertSame('private', $file->disk);
        $this->assertStringStartsWith($folder . '/', $file->path);
        $this->seeInDatabase('files', ['stored_file_id' => $file->id, 'folder' => $folder]);

        $home = $this->folders()->find('private', 'users/' . $ana->id);
        $this->assertNotNull($home);
        $this->assertSame((int) $ana->id, $home['owner_id'], 'The personal space is created for its owner.');
        $avatar = $this->folders()->find('private', $folder);
        $this->assertNotNull($avatar);
        $this->assertTrue($avatar['own_permissions']);
        $this->assertSame([['subject_type' => 'authenticated', 'subject_id' => '', 'rights' => ['view']]], $this->folders()->permissions($avatar['id']));

        // Every signed-in user may view it, nobody else may change it; the rest of the space stays private.
        $bob = $this->createUser('bob@example.com', 'user');
        $acl = new FolderAcl();
        $this->assertSame([Rights::VIEW], $acl->rights($bob, 'private', $folder));
        $this->assertSame([], $acl->rights($bob, 'private', 'users/' . $ana->id));
        $this->assertTrue(service('storage')->canView($bob, $file));
        $this->assertFalse(service('storage')->canView(null, $file), 'Guests never.');
        $this->seeInDatabase('activity_log', ['module' => 'Files', 'action' => 'file_uploaded', 'user_id' => $ana->id]);
    }

    public function testAnUploadCannotTargetAnotherFolder(): void
    {
        $ana   = $this->createUser('ana@example.com', 'user');
        $token = Avatars::field()->scope($ana)->encode();
        $this->login('ana@example.com');

        // Folder or disk parameters next to the token are ignored; a changed token is refused.
        $this->send('POST', 'files/picker/upload', ['token' => $token, 'folder' => 'shared', 'disk' => 'public', 'path' => 'shared']);
        $this->assertSame(0, db_connect()->table('stored_files')->where('disk', 'public')->countAllResults());

        $forged = Avatars::field()->folder('users/{user_id}/../1/avatar');

        try {
            $forged->scope($ana);
            $this->fail('Traversal in a folder is refused.');
        } catch (\Modules\Core\Storage\StorageException) {
            $this->addToAssertionCount(1);
        }

        [$payload, $signature] = explode('.', $token);
        $data                  = json_decode((string) base64_decode(strtr($payload, '-_', '+/'), true), true);
        $data['f']             = 'shared';
        $tampered              = rtrim(strtr(base64_encode((string) json_encode($data)), '+/', '-_'), '=') . '.' . $signature;
        $this->assertSame(403, $this->send('POST', 'files/picker/upload', ['token' => $tampered])->response()->getStatusCode());
    }

    public function testQuotasApplyToPickerUploads(): void
    {
        $ana = $this->createUser('ana@example.com', 'user');
        $this->folder('private', 'users/' . $ana->id, [], 50);

        $this->expectException(RuntimeException::class);
        $this->pick($ana, PickerField::make('doc')->disk('private')->folder('users/{user_id}/docs')->types('txt'), str_repeat('x', 100), 'big.txt');
    }

    public function testBrowsingAndChoosingFollowTheRootsAndTheAcl(): void
    {
        $editor = $this->createUser('ed@example.com', 'editor');
        $other  = $this->createUser('other@example.com', 'superadmin');
        $field  = PickerField::make('cover')->mode('both')->disk('public')->folder('modules/demo')->types('jpg,png,txt')->browseRoot(['modules/demo', 'shared']);
        $this->folder('public', 'shared', []);
        $this->folder('public', 'shared/Images', [['group', 'editor', 'view']]);
        $this->folder('public', 'shared/Hidden', [['group', 'admin', 'view']]);
        $visible = $this->upload($other, 'public', 'shared/Images', 'visible', 'a.txt');
        $hidden  = $this->upload($other, 'public', 'shared/Hidden', 'hidden', 'b.txt');
        $mine    = $this->pick($editor, $field, 'own', 'c.txt');
        $backend = PickerField::backend();
        $scope   = $field->scope($editor);
        $stored  = static fn (int $id): StoredFile => service('storage')->findById((int) db_connect()->table('files')->where('id', $id)->get()->getRowArray()['stored_file_id']);

        $own = $backend->browse($editor, $scope, 'modules/demo');
        $this->assertNotNull($own, 'The field\'s own folder is always listed.');
        $this->assertSame([$mine->key], array_map(static fn (StoredFile $file): string => $file->key, $own['files']));

        $shared = $backend->browse($editor, $scope, 'shared');
        $this->assertNull($shared, 'shared itself has an empty ACL.');
        $images = $backend->browse($editor, $scope, 'shared/Images');
        $this->assertNotNull($images);
        $this->assertCount(1, $images['files']);
        $this->assertNull($backend->browse($editor, $scope, 'shared/Hidden'));

        $this->assertTrue($backend->canChoose($editor, $scope, $stored($visible)));
        $this->assertFalse($backend->canChoose($editor, $scope, $stored($hidden)), 'A folder without view');
        $private = $this->pick($editor, Avatars::field(), self::png(), 'me.png');
        $this->assertFalse($backend->canChoose($editor, $scope, $private), 'Another disk');
        $this->assertTrue($field->accepts($editor, $stored($visible)->key));
        $this->assertFalse($field->accepts($editor, $stored($hidden)->key));

        // Over HTTP: outside the roots is a 404.
        $this->login('ed@example.com');
        $token = $field->scope($editor)->encode();
        $this->assertSame(200, $this->send('GET', 'files/picker/browse', ['token' => $token, 'path' => 'shared/Images'])->response()->getStatusCode());
        $this->assertSame(404, $this->send('GET', 'files/picker/browse', ['token' => $token, 'path' => 'shared/Hidden'])->response()->getStatusCode());
        $this->assertSame(404, $this->send('GET', 'files/picker/browse', ['token' => $token, 'path' => 'users/' . $editor->id])->response()->getStatusCode());
    }

    public function testDiscardingAFileRemovesItsRow(): void
    {
        $ana  = $this->createUser('ana@example.com', 'user');
        $file = $this->pick($ana, Avatars::field(), self::png(), 'me.png');

        PickerField::backend()->discard($file);
        $this->dontSeeInDatabase('stored_files', ['id' => $file->id]);
        $this->dontSeeInDatabase('files', ['stored_file_id' => $file->id]);
    }
}
