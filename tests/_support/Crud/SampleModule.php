<?php

declare(strict_types=1);

namespace Tests\Support\Crud;

use CodeIgniter\I18n\Time;
use CodeIgniter\Router\RouteCollection;
use Config\Database;
use Modules\Core\Cache\CoreCache;
use Modules\Core\Crud\CrudFactory;
use Modules\Core\Permissions\CatalogueBuilder;
use Modules\Core\Permissions\CrudPermissions;
use Modules\Core\Permissions\PermissionCatalogue;
use Modules\Core\Permissions\ShieldMatrix;

/**
 * Installs the test-only "Sample" module in a migrated test database: the `sample_notes` table,
 * the registry row, the permissions its manifest generates and Shield's matrix; and registers
 * its CRUD routes the way a module's `Config/Routes.php` does.
 */
final class SampleModule
{
    public static function install(): void
    {
        $db    = Database::connect();
        $forge = Database::forge();
        $forge->dropTable('sample_notes', true);
        $forge->addField([
            'id'       => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'title'    => ['type' => 'VARCHAR', 'constraint' => 150],
            'body'     => ['type' => 'TEXT', 'null' => true],
            'author'   => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'status'   => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'draft'],
            'owner_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
        ]);
        $forge->addPrimaryKey('id');
        $forge->createTable('sample_notes');

        $now = Time::now()->toDateTimeString();
        $db->table('modules')->where('name', 'Sample')->delete();
        $db->table('modules')->insert([
            'name' => 'Sample', 'version' => '1.0.0', 'enabled' => 1,
            'grants_applied' => 1, 'installed_at' => $now, 'updated_at' => $now,
        ]);

        $catalogue = new PermissionCatalogue();
        $catalogue->upsert((new CatalogueBuilder())->build(['Sample' => new SampleManifest()]));
        (new CoreCache())->invalidate('permissions');
        (new ShieldMatrix($catalogue))->write();
        CrudPermissions::reset();
    }

    public static function routes(RouteCollection $routes): void
    {
        $handler = '\\' . SampleNotes::class;

        CrudFactory::routes($routes, 'sample-notes', 'backend/sample/notes', $handler . '::index');
        CrudFactory::routes($routes, 'sample-mine', 'backend/sample/mine', $handler . '::mine');
    }
}
