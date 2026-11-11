<?php

declare(strict_types=1);

namespace Modules\Files\Tests;

use CodeIgniter\Shield\Entities\User;
use Modules\Core\Storage\StoredFile;
use Modules\Files\Policies\FileAccessPolicy;
use Modules\Files\Policies\PolicyRegistry;
use Modules\Files\Services\FolderAcl;
use Modules\Files\Services\Rights;
use Modules\Files\Services\Spaces;

/**
 * The folder ACL: inheritance and its override, the three subjects, `files.all` and superadmins,
 * the personal space and its group setting, the RBAC ceiling, `files.private`, module folders and
 * default deny.
 *
 * @internal
 */
final class FolderAclTest extends FilesTestCase
{
    public function testSubfoldersInheritUnlessTheyDefineTheirOwn(): void
    {
        $this->folder('private', 'shared/Team', [['group', 'editor', 'view,upload']]);
        $this->folder('private', 'shared/Team/Inherits');
        $this->folder('private', 'shared/Team/Inherits/Deeper');
        $this->folder('private', 'shared/Team/Own', [['group', 'admin', 'view']]);
        $editor = $this->createUser('ed@example.com', 'editor');
        $acl    = new FolderAcl();

        $this->assertSame(['view', 'upload'], $acl->rights($editor, 'private', 'shared/Team'));
        $this->assertSame(['view', 'upload'], $acl->rights($editor, 'private', 'shared/Team/Inherits'));
        $this->assertSame(['view', 'upload'], $acl->rights($editor, 'private', 'shared/Team/Inherits/Deeper'));
        $this->assertSame([], $acl->rights($editor, 'private', 'shared/Team/Own'), 'An own ACL replaces the inherited one.');
        $this->assertSame([], $acl->rights($editor, 'public', 'shared/Team'), 'Folders are per disk.');

        // Turning the override off inherits again.
        $own = $this->folders()->find('private', 'shared/Team/Own');
        $this->assertNotNull($own);
        $this->folders()->savePermissions($own['id'], false, []);
        $this->assertSame(['view', 'upload'], (new FolderAcl())->rights($editor, 'private', 'shared/Team/Own'));
    }

    public function testAnOwnAclWithoutEntriesHidesTheFolder(): void
    {
        $this->folder('private', 'shared/Open', [['authenticated', '', 'view']]);
        $this->folder('private', 'shared/Open/Closed', []);
        $user = $this->createUser('ana@example.com', 'user');

        $this->assertSame(['view'], (new FolderAcl())->rights($user, 'private', 'shared/Open'));
        $this->assertSame([], (new FolderAcl())->rights($user, 'private', 'shared/Open/Closed'));
    }

    public function testGroupUserAndAllLoggedInSubjectsAddUp(): void
    {
        $ana  = $this->createUser('ana@example.com', 'user');
        $joan = $this->createUser('joan@example.com', 'editor');
        $this->folder('private', 'shared/Mix', [
            ['authenticated', '', 'view'],
            ['group', 'editor', 'upload'],
            ['user', (string) $ana->id, 'edit,delete'],
        ]);
        $acl = new FolderAcl();

        $this->assertSame(['view', 'edit', 'delete'], $acl->rights($ana, 'private', 'shared/Mix'));
        $this->assertSame(['view', 'upload'], $acl->rights($joan, 'private', 'shared/Mix'));
        $this->assertSame([], $acl->rights(null, 'private', 'shared/Mix'), 'Guests are never "logged-in users".');
    }

    public function testNoRegisteredFolderMeansNoRights(): void
    {
        $user = $this->createUser('ana@example.com', 'editor');

        $this->assertSame([], (new FolderAcl())->rights($user, 'private', 'shared/Nowhere'));
        $this->assertFalse((new FolderAcl())->governs('private', 'shared/Nowhere'));
    }

    public function testFilesAllAndSuperadminsGetEveryRight(): void
    {
        $this->folder('private', 'shared/Secret', []);
        $admin = $this->createUser('boss@example.com', 'admin');
        $super = $this->createUser('root@example.com', 'superadmin');
        $other = $this->createUser('ana@example.com', 'user');

        $this->assertSame(Rights::ALL, (new FolderAcl())->rights($admin, 'private', 'shared/Secret'));
        $this->assertSame(Rights::ALL, (new FolderAcl())->rights($super, 'private', 'shared/Secret'));
        $this->assertSame(Rights::ALL, (new FolderAcl())->rights($admin, 'private', Spaces::personalPath((int) $other->id)), 'files.all reaches personal spaces too.');
    }

    public function testThePersonalSpaceBelongsToItsOwner(): void
    {
        $ana  = $this->createUser('ana@example.com', 'editor');
        $joan = $this->createUser('joan@example.com', 'editor');
        $home = Spaces::personalPath((int) $ana->id);
        $this->folder('private', $home, []);
        $acl = new FolderAcl();

        $this->assertSame(Rights::ALL, $acl->rights($ana, 'private', $home));
        $this->assertSame(Rights::ALL, $acl->rights($ana, 'private', $home . '/Fotos/2026'));
        $this->assertSame([], $acl->rights($joan, 'private', $home));
        $this->assertSame([], $acl->rights($ana, 'public', $home), 'Personal spaces live on the private disk.');

        // A subfolder may share something (e.g. the avatar folder of session 23).
        $this->folder('private', $home . '/avatar', [['authenticated', '', 'view']]);
        $this->assertSame(['view'], (new FolderAcl())->rights($joan, 'private', $home . '/avatar'));
        $this->assertSame(Rights::ALL, (new FolderAcl())->rights($ana, 'private', $home . '/avatar'), 'The owner keeps every right.');
    }

    public function testThePersonalSpaceInTheFileManagerFollowsTheGroupSetting(): void
    {
        $user = $this->createUser('ana@example.com', 'user');
        $user->addPermission('files.list', 'files.upload');
        $home = Spaces::personalPath((int) $user->id);

        $this->assertSame([], (new FolderAcl())->managerRights($user, 'private', $home), 'Group `user` is not in Files.personalSpaceGroups by default.');
        $this->assertSame(Rights::ALL, (new FolderAcl())->rights($user, 'private', $home), 'The ACL itself (serving files) still applies.');

        $this->setSetting('Files.personalSpaceGroups', ['user']);
        $this->assertSame(['view', 'upload'], (new FolderAcl())->managerRights($user, 'private', $home), 'Capped by RBAC: no files.edit, files.delete or files.acl.');
    }

    public function testRbacIsTheCeiling(): void
    {
        $this->folder('private', 'shared/All', [['group', 'user', 'view,upload,edit,delete,manage']]);
        $user = $this->createUser('ana@example.com', 'user');
        $acl  = new FolderAcl();
        $this->setSetting('Files.personalSpaceGroups', []);

        $this->assertSame([], $acl->managerRights($user, 'private', 'shared/All'), 'Without files.list nothing in the file manager.');

        $user->addPermission('files.list', 'files.edit', 'files.private');
        $this->assertSame(['view', 'edit'], (new FolderAcl())->managerRights($user, 'private', 'shared/All'));

        $user->addPermission('files.acl');
        $this->assertContains('manage', (new FolderAcl())->managerRights($user, 'private', 'shared/All'));
    }

    public function testSharedFoldersOfThePrivateDiskNeedFilesPrivate(): void
    {
        $this->folder('private', 'shared/Docs', [['group', 'user', 'view']]);
        $this->folder('public', 'shared/Web', [['group', 'user', 'view']]);
        $user = $this->createUser('ana@example.com', 'user');
        $user->addPermission('files.list');

        $this->assertSame([], (new FolderAcl())->managerRights($user, 'private', 'shared/Docs'));
        $this->assertSame(['view'], (new FolderAcl())->managerRights($user, 'public', 'shared/Web'));
        $this->assertSame(['view'], (new FolderAcl())->rights($user, 'private', 'shared/Docs'), 'Serving the files by URL does not need it.');

        $user->addPermission('files.private');
        $this->assertSame(['view'], (new FolderAcl())->managerRights($user, 'private', 'shared/Docs'));
    }

    public function testModuleFoldersAreHiddenOrReadOnly(): void
    {
        $admin = $this->createUser('boss@example.com', 'admin');
        $this->folder('public', 'modules/news', [['group', 'admin', 'view,upload,edit,delete,manage']]);

        $this->assertSame([], (new FolderAcl())->rights($admin, 'public', 'modules/news'), 'Hidden without a policy, whatever the ACL says.');

        PolicyRegistry::reset();
        \CodeIgniter\Events\Events::on(PolicyRegistry::EVENT, static function (PolicyRegistry $registry): void {
            $registry->register(new class () implements FileAccessPolicy {
                public function folder(): string
                {
                    return 'modules/news';
                }

                public function browse(): string
                {
                    return 'readonly';
                }

                public function canView(?User $user, StoredFile $file): bool
                {
                    return false;
                }
            });
        });

        $this->assertSame(['view'], (new FolderAcl())->rights($admin, 'public', 'modules/news/2026'));
        $this->assertSame([], (new FolderAcl())->rights($this->createUser('ed@example.com', 'editor'), 'public', 'modules/news'));
    }
}
