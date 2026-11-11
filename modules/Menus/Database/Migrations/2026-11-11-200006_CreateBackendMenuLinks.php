<?php

declare(strict_types=1);

namespace Modules\Menus\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Table `backend_menu_links`: custom groups (`kind` `group`: a heading) and custom links (`kind`
 * `link`) of the back-office menu, shown as entries `custom.<id>`. A link's `type` is `url` (an
 * external URL or an internal path), `route` or a module link type and `value` its target;
 * `permission` (optional, from the catalogue) and `groups` (JSON list of group names, empty =
 * everyone) restrict who sees it; `parent`, `zone` and `position` are its default place.
 */
final class CreateBackendMenuLinks extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'         => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'kind'       => ['type' => 'VARCHAR', 'constraint' => 10, 'default' => 'link'],
            'type'       => ['type' => 'VARCHAR', 'constraint' => 60, 'default' => 'url'],
            'value'      => ['type' => 'VARCHAR', 'constraint' => 500, 'default' => ''],
            'labels'     => ['type' => 'TEXT', 'null' => true],
            'icon'       => ['type' => 'VARCHAR', 'constraint' => 60, 'default' => ''],
            'target'     => ['type' => 'VARCHAR', 'constraint' => 10, 'default' => '_self'],
            'permission' => ['type' => 'VARCHAR', 'constraint' => 150, 'default' => ''],
            'groups'     => ['type' => 'TEXT', 'null' => true],
            'parent'     => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
            'zone'       => ['type' => 'VARCHAR', 'constraint' => 30, 'default' => 'sidebar'],
            'position'   => ['type' => 'INT', 'constraint' => 11, 'default' => 100],
            'created_by' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->createTable('backend_menu_links');
    }

    public function down(): void
    {
        $this->forge->dropTable('backend_menu_links', true);
    }
}
