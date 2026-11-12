<?php

declare(strict_types=1);

namespace Modules\Files\Tests;

use Modules\Files\Services\Spaces;

/**
 * The file manager's routes: the RBAC filters, the folder ACL on every request (forged requests
 * included), traversal, metadata, moves, the trash, folders and the permissions editor.
 *
 * @internal
 */
final class FileApiTest extends FilesTestCase
{
    public function testTheFileManagerNeedsFilesList(): void
    {
        $this->loginWith();
        $this->assertNotSame(200, $this->statusOf('GET', 'backend/files'));
        $this->assertNotSame(200, $this->statusOf('GET', 'backend/files/api/tree'));

        $this->loginAsEditor();
        $result = $this->send('GET', 'backend/files');
        $result->assertStatus(200);
        $result->assertSee('data-component="files/file-manager"', null);
        $this->assertStringNotContainsString(' style="', (string) $result->response()->getBody());
    }

    public function testTheTreeShowsWhatTheAclAllows(): void
    {
        $this->folder('public', 'shared', []);
        $this->folder('public', 'shared/Web', [['group', 'editor', 'view,upload']]);
        $this->folder('public', 'shared/Hidden', [['group', 'admin', 'view']]);
        $this->folder('public', 'shared/Hidden/ForEditors', [['group', 'editor', 'view']]);
        $editor = $this->loginAsEditor();

        $tree  = $this->apiJson('GET', 'tree')['tree'];
        $paths = self::paths($tree);

        $this->assertContains('private:' . Spaces::personalPath((int) $editor->id), $paths, 'The personal space (editor is in Files.personalSpaceGroups).');
        $this->assertContains('public:shared/Web', $paths);
        $this->assertContains('public:shared/Hidden', $paths, 'Shown only as the way to a visible subfolder.');
        $this->assertContains('public:shared/Hidden/ForEditors', $paths);
        $this->assertNotContains('private:shared', $paths, 'The private root has no entry for editors and nothing visible below it.');

        $this->apiJson('GET', 'list', ['disk' => 'public', 'path' => 'shared/Hidden'], 404);
        $listing = $this->apiJson('GET', 'list', ['disk' => 'public', 'path' => 'shared/Web']);
        $this->assertSame(['view', 'upload'], $listing['rights']);
    }

    public function testTraversalAndPathsOutsideTheSpacesAreRefused(): void
    {
        $this->folder('public', 'shared/Web', [['group', 'editor', 'view']]);
        $this->loginAsEditor();

        foreach (['shared/../users/1', '../etc', '/etc/passwd', 'shared/.hidden', 'shared/Web/..', 'shared\\Web', 'etc', 'shared/Web%00'] as $path) {
            $this->apiJson('GET', 'list', ['disk' => 'public', 'path' => $path], 404);
        }

        $this->apiJson('GET', 'list', ['disk' => 'local', 'path' => 'shared/Web'], 404);
        $this->apiJson('POST', 'folder', ['disk' => 'public', 'path' => 'shared/Web', 'name' => '../x'], 403);
    }

    public function testMetadataMoveTrashAndRestore(): void
    {
        $this->folder('public', 'shared/A', [['group', 'editor', 'view,upload,edit,delete']]);
        $this->folder('public', 'shared/B', [['group', 'editor', 'view,upload']]);
        $this->folder('public', 'shared/C', [['group', 'editor', 'view']]);
        $editor = $this->loginAsEditor();
        $id     = $this->upload($editor, 'public', 'shared/A', 'hello', 'nota.txt');

        $item = $this->apiJson('GET', 'file/' . $id);
        $this->assertSame('nota.txt', $item['name']);
        $this->assertStringContainsString('/uploads/shared/A/', (string) $item['publicUrl']);

        $saved = $this->apiJson('POST', 'file/' . $id . '/update', ['name' => 'acta', 'alt' => 'Acta', 'tags' => 'Actes, 2026, actes']);
        $this->assertSame('acta.txt', $saved['file']['name'], 'The stored extension is kept.');
        $this->assertSame(['actes', '2026'], $saved['file']['tags']);

        $found = $this->apiJson('GET', 'list', ['disk' => 'public', 'path' => 'shared/A', 'tag' => 'actes', 'type' => 'document']);
        $this->assertCount(1, $found['files']);
        $this->assertCount(0, $this->apiJson('GET', 'list', ['disk' => 'public', 'path' => 'shared/A', 'type' => 'image'])['files']);
        $this->assertCount(0, $this->apiJson('GET', 'list', ['disk' => 'public', 'path' => 'shared/A', 'q' => 'zzz'])['files']);

        $this->apiJson('POST', 'file/' . $id . '/move', ['to' => 'shared/C'], 403);
        $this->apiJson('POST', 'file/' . $id . '/move', ['to' => 'shared/Nope'], 404);
        $this->apiJson('POST', 'file/' . $id . '/move', ['to' => 'shared/B']);
        $this->seeInDatabase('files', ['id' => $id, 'folder' => 'shared/B']);

        // In B the editor has no edit or delete right.
        $this->apiJson('POST', 'file/' . $id . '/update', ['name' => 'x'], 403);
        $this->apiJson('POST', 'file/' . $id . '/delete', [], 403);
        db_connect()->table('files')->where('id', $id)->update(['folder' => 'shared/A']);

        $this->apiJson('POST', 'file/' . $id . '/delete');
        $this->assertCount(0, $this->apiJson('GET', 'list', ['disk' => 'public', 'path' => 'shared/A'])['files']);
        $this->assertCount(1, $this->apiJson('GET', 'trash')['files']);
        $this->apiJson('POST', 'file/' . $id . '/restore');
        $this->assertCount(1, $this->apiJson('GET', 'list', ['disk' => 'public', 'path' => 'shared/A'])['files']);
        $this->seeInDatabase('activity_log', ['module' => 'Files', 'action' => 'file_restored']);
    }

    public function testForgedRequestsWithoutTheRbacPermissionAreRefused(): void
    {
        $this->folder('public', 'shared/A', [['group', 'user', 'view,upload,edit,delete,manage']]);
        $owner = $this->createUser('ed@example.com', 'editor');
        $this->folder('public', 'shared/E', [['group', 'editor', 'view,upload']]);
        $id = $this->upload($owner, 'public', 'shared/E', 'hello', 'a.txt');
        db_connect()->table('files')->where('id', $id)->update(['folder' => 'shared/A']);

        $this->loginWith('files.list');

        $this->assertSame(403, $this->statusOf('POST', 'backend/files/api/file/' . $id . '/update', ['name' => 'x']));
        $this->assertSame(403, $this->statusOf('POST', 'backend/files/api/file/' . $id . '/delete'));
        $this->assertSame(403, $this->statusOf('POST', 'backend/files/api/folder', ['disk' => 'public', 'path' => 'shared/A', 'name' => 'X']));
        $this->assertSame(403, $this->statusOf('POST', 'backend/files/api/acl', ['disk' => 'public', 'path' => 'shared/A']));
        $this->assertSame(403, $this->statusOf('POST', 'backend/files/api/upload', ['disk' => 'public', 'path' => 'shared/A']));
        $this->seeInDatabase('files', ['id' => $id, 'name' => 'a.txt', 'deleted_at' => null]);
    }

    public function testFilesOfFoldersTheUserCannotSeeAnswer404(): void
    {
        $owner = $this->createUser('ed@example.com', 'editor');
        $this->folder('public', 'shared/Private', [['user', (string) $owner->id, 'view,upload,edit,delete']]);
        $id = $this->upload($owner, 'public', 'shared/Private', 'secret', 'secret.txt');

        $this->loginAsEditor('other@example.com');

        foreach (['GET file/' . $id, 'GET file/' . $id . '/download', 'GET file/' . $id . '/preview', 'POST file/' . $id . '/update', 'POST file/' . $id . '/delete', 'POST file/' . $id . '/move'] as $request) {
            [$method, $path] = explode(' ', $request);
            $this->apiJson($method, $path, $method === 'POST' ? ['name' => 'x', 'to' => 'shared/Private'] : [], 404);
        }
    }

    public function testDownloadSendsTheFileWithSafeHeaders(): void
    {
        $editor = $this->loginAsEditor();
        $this->folder('public', 'shared/A', [['group', 'editor', 'view,upload']]);
        $id = $this->upload($editor, 'public', 'shared/A', "a,b\n1,2\n", 'dades.csv');

        $download = $this->send('GET', 'backend/files/api/file/' . $id . '/download');
        $download->assertStatus(200);
        $this->assertStringStartsWith('attachment; filename="dades.csv"', $download->response()->getHeaderLine('Content-Disposition'));
        $this->assertSame('nosniff', $download->response()->getHeaderLine('X-Content-Type-Options'));

        $preview = $this->send('GET', 'backend/files/api/file/' . $id . '/preview');
        $this->assertStringStartsWith('text/plain', $preview->response()->getHeaderLine('Content-Type'), 'Text is previewed as plain text.');
        $this->assertStringStartsWith('inline', $preview->response()->getHeaderLine('Content-Disposition'));
        $this->assertStringContainsString('sandbox', $preview->response()->getHeaderLine('Content-Security-Policy'));
    }

    public function testFolders(): void
    {
        $editor = $this->loginAsEditor();
        $home   = Spaces::personalPath((int) $editor->id);
        $this->apiJson('GET', 'tree');

        $created = $this->apiJson('POST', 'folder', ['disk' => 'private', 'path' => $home, 'name' => 'Fotos 2026'], 201);
        $this->assertSame($home . '/Fotos 2026', $created['path']);
        $this->apiJson('POST', 'folder', ['disk' => 'private', 'path' => $home, 'name' => 'Fotos 2026'], 422);
        $this->apiJson('POST', 'folder', ['disk' => 'private', 'path' => $home, 'name' => '.git'], 422);
        $this->apiJson('POST', 'folder', ['disk' => 'private', 'path' => $home, 'name' => 'a/b'], 422);

        $id = $this->upload($editor, 'private', $home . '/Fotos 2026', 'Some plain text.', 'a.txt');
        $renamed = $this->apiJson('POST', 'folder/rename', ['disk' => 'private', 'path' => $home . '/Fotos 2026', 'name' => 'Fotos']);
        $this->assertSame($home . '/Fotos', $renamed['path']);
        $this->seeInDatabase('files', ['id' => $id, 'folder' => $home . '/Fotos']);

        $this->apiJson('POST', 'folder/delete', ['disk' => 'private', 'path' => $home . '/Fotos'], 422);
        $this->apiJson('POST', 'file/' . $id . '/delete');
        $this->apiJson('POST', 'folder/delete', ['disk' => 'private', 'path' => $home . '/Fotos'], 422);
        $this->apiJson('POST', 'file/' . $id . '/purge');
        $this->apiJson('POST', 'folder/delete', ['disk' => 'private', 'path' => $home . '/Fotos']);
        $this->apiJson('POST', 'folder/delete', ['disk' => 'private', 'path' => $home], 403);
        $this->dontSeeInDatabase('folders', ['path' => $home . '/Fotos']);

        // Someone else's personal space is not reachable.
        $other = $this->createUser('joan@example.com', 'editor');
        $this->folder('private', Spaces::personalPath((int) $other->id), []);
        $this->apiJson('POST', 'folder', ['disk' => 'private', 'path' => Spaces::personalPath((int) $other->id), 'name' => 'X'], 404);
    }

    public function testThePermissionsEditor(): void
    {
        $this->folder('public', 'shared', []);
        $this->folder('public', 'shared/Team', [['group', 'editor', 'view']]);
        $this->loginAsEditor();
        $this->apiJson('GET', 'acl', ['disk' => 'public', 'path' => 'shared/Team'], 403);

        $admin = $this->createUser('boss@example.com', 'admin');
        $this->login('boss@example.com');
        $acl = $this->apiJson('GET', 'acl', ['disk' => 'public', 'path' => 'shared/Team']);
        $this->assertTrue($acl['own']);
        $this->assertSame([['subject_type' => 'group', 'subject_id' => 'editor', 'rights' => ['view']]], $acl['entries']);

        $this->send('POST', 'backend/files/api/acl', ['disk' => 'public', 'path' => 'shared/Team', 'own' => '1', 'entries' => [['subject_type' => 'group', 'subject_id' => 'nobody', 'rights' => ['view']]]])->assertStatus(422);
        $this->apiJson('POST', 'acl', [
            'disk' => 'public', 'path' => 'shared/Team', 'own' => '1', 'quota' => '5',
            'entries' => [
                ['subject_type' => 'group', 'subject_id' => 'editor', 'rights' => ['view', 'upload', 'bogus']],
                ['subject_type' => 'user', 'subject_id' => (string) $admin->id, 'rights' => ['manage']],
                ['subject_type' => 'authenticated', 'subject_id' => 'ignored', 'rights' => ['view']],
            ],
        ]);

        $folder = $this->folders()->find('public', 'shared/Team');
        $this->assertNotNull($folder);
        $this->assertSame(5 * 1048576, $folder['quota']);
        $this->assertSame([
            ['subject_type' => 'authenticated', 'subject_id' => '', 'rights' => ['view']],
            ['subject_type' => 'group', 'subject_id' => 'editor', 'rights' => ['view', 'upload']],
            ['subject_type' => 'user', 'subject_id' => (string) $admin->id, 'rights' => ['manage']],
        ], $this->folders()->permissions($folder['id']));
        $this->seeInDatabase('activity_log', ['module' => 'Files', 'action' => 'acl_changed', 'type' => 'security']);

        // Inherit again.
        $this->apiJson('POST', 'acl', ['disk' => 'public', 'path' => 'shared/Team', 'own' => '0']);
        $this->assertSame([], $this->folders()->permissions($folder['id']));
    }

    public function testOnlySuperadminsSeeOtherPersonalSpacesInTheTree(): void
    {
        $owner = $this->createUser('ed@example.com', 'editor');
        $home  = Spaces::personalPath((int) $owner->id);
        $this->folder('private', $home, []);

        $this->createUser('boss@example.com', 'admin');
        $this->login('boss@example.com');
        $this->assertNotContains('private:' . $home, self::paths($this->apiJson('GET', 'tree')['tree']));
        $this->apiJson('GET', 'list', ['disk' => 'private', 'path' => $home], 404);

        $this->createUser('root@example.com', 'superadmin');
        $this->login('root@example.com');
        $this->assertContains('private:' . $home, self::paths($this->apiJson('GET', 'tree')['tree']));
        $this->apiJson('GET', 'list', ['disk' => 'private', 'path' => $home]);
        $this->apiJson('GET', 'acl', ['disk' => 'private', 'path' => $home]);
    }

    private function loginAsEditor(string $email = 'ed@example.com'): \CodeIgniter\Shield\Entities\User
    {
        $user = $this->createUser($email, 'editor');
        $this->login($email);

        return $user;
    }

    /**
     * @param list<array<string, mixed>> $nodes
     *
     * @return list<string>
     */
    private static function paths(array $nodes): array
    {
        $paths = [];

        foreach ($nodes as $node) {
            $paths[] = $node['disk'] . ':' . $node['path'];
            $paths   = [...$paths, ...self::paths(is_array($node['children'] ?? null) ? $node['children'] : [])];
        }

        return $paths;
    }
}
