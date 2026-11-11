<?php

declare(strict_types=1);

namespace Modules\Files\Tests;

use CodeIgniter\Database\Migration;
use Config\Database;
use Modules\Core\Demo\DemoDataManager;
use Modules\Core\Modules\ManifestValidator;
use Modules\Files\Config\Module;
use Modules\Files\Services\FolderAcl;

/**
 * The Files module itself: manifest, permission names and default grants, the settings, the
 * demo data (inheritance and override to try) and the migrations (down and up again).
 *
 * @internal
 */
final class FilesModuleTest extends FilesTestCase
{
    public function testManifestIsValid(): void
    {
        $this->assertSame([], (new ManifestValidator())->validate(new Module(), dirname(__DIR__)));
    }

    public function testPermissionNamesAndGrants(): void
    {
        $catalogue = array_column(db_connect()->table('permissions')->like('name', 'files.', 'after')->get()->getResultArray(), 'name');
        sort($catalogue);
        $this->assertSame(['files.acl', 'files.all', 'files.delete', 'files.edit', 'files.folders', 'files.list', 'files.private', 'files.upload'], $catalogue);

        $editor = $this->createUser('ed@example.com', 'editor');
        $this->assertTrue($editor->can('files.list'));
        $this->assertTrue($editor->can('files.folders'));
        $this->assertTrue($editor->can('files.private'));
        $this->assertFalse($editor->can('files.acl'));
        $this->assertFalse($editor->can('files.all'));
        $this->assertTrue($this->createUser('boss@example.com', 'admin')->can('files.all'));
        $this->assertFalse($this->createUser('ana@example.com', 'user')->can('files.list'));
    }

    public function testSettingDefaults(): void
    {
        $this->assertSame(['admin', 'editor'], setting('Files.personalSpaceGroups'));
        $this->assertSame(100, setting('Files.personalQuota'));
    }

    public function testDemoDataShowsInheritanceAndIsRemovedCompletely(): void
    {
        $manager = new DemoDataManager(null, 'development');
        $this->assertTrue($manager->load('Files'));

        $editor = $this->createUser('ed@example.com', 'editor');
        $acl    = new FolderAcl();
        $this->assertSame(['view', 'upload', 'edit'], $acl->rights($editor, 'private', 'shared/Secretaria/Circulars'), 'Inherited from Secretaria.');
        $this->assertSame([], $acl->rights($editor, 'private', 'shared/Secretaria/Actes'), 'Own permissions: admins only.');
        $this->assertSame(6, db_connect()->table('files')->countAllResults());
        $this->assertSame(2, db_connect()->table('files')->where('thumbnail IS NOT NULL')->countAllResults());

        $manager->remove('Files');

        foreach (['files', 'folder_permissions', 'stored_files'] as $table) {
            $this->assertSame(0, db_connect()->table($table)->countAllResults(), $table);
        }

        $this->assertSame(0, db_connect()->table('folders')->like('path', 'shared/', 'after')->countAllResults());
    }

    public function testMigrationsGoDownAndUpAgain(): void
    {
        $files      = glob(dirname(__DIR__) . '/Database/Migrations/*.php') ?: [];
        $migrations = [];
        $tables     = ['folders', 'folder_permissions', 'files'];

        foreach ($files as $file) {
            $class = 'Modules\Files\Database\Migrations\\' . preg_replace('/^[\d_-]+/', '', basename($file, '.php'));

            if (! class_exists($class, false)) {
                require_once $file;
            }

            $migrations[] = new $class(Database::forge('tests'));
        }

        $this->assertCount(3, $migrations);

        foreach (array_reverse($migrations) as $migration) {
            \assert($migration instanceof Migration);
            $migration->down();
        }

        $this->db->resetDataCache();

        foreach ($tables as $table) {
            $this->assertFalse($this->db->tableExists($table), $table);
        }

        foreach ($migrations as $migration) {
            $migration->up();
        }

        $this->db->resetDataCache();

        foreach ($tables as $table) {
            $this->assertTrue($this->db->tableExists($table), $table);
        }
    }
}
