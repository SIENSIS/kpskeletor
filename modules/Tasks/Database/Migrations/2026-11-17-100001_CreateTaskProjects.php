<?php

declare(strict_types=1);

namespace Modules\Tasks\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Table `task_projects` (tutorial 1): the projects that group to-do tasks. The name and the
 * description are translated (`task_project_translations`, tutorial 2).
 *
 * - `slug`: the project's public URL `tasks/project/<slug>` (tutorial 5), unique;
 * - `color`: `#rrggbb`, used by the board and the calendars;
 * - `ends_on`: the project's deadline, offered to the calendar as a provider (pull, tutorial 6);
 * - `active`: inactive projects are hidden from the board and the public page.
 */
final class CreateTaskProjects extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'         => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'slug'       => ['type' => 'VARCHAR', 'constraint' => 190],
            'color'      => ['type' => 'VARCHAR', 'constraint' => 7, 'default' => '#0d6efd'],
            'ends_on'    => ['type' => 'DATE', 'null' => true],
            'active'     => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('slug');
        $this->forge->createTable('task_projects');
    }

    public function down(): void
    {
        $this->forge->dropTable('task_projects', true);
    }
}
