<?php

declare(strict_types=1);

namespace Modules\Tasks\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Table `task_project_translations` (tutorial 2, a translated field): the name and description of
 * a project per language (kpcrudy `setTranslations()`; Catalan required).
 */
final class CreateTaskProjectTranslations extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'          => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'project_id'  => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'locale'      => ['type' => 'VARCHAR', 'constraint' => 10],
            'name'        => ['type' => 'VARCHAR', 'constraint' => 120],
            'description' => ['type' => 'TEXT', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey(['project_id', 'locale']);
        $this->forge->addForeignKey('project_id', 'task_projects', 'id', '', 'CASCADE');
        $this->forge->createTable('task_project_translations');
    }

    public function down(): void
    {
        $this->forge->dropTable('task_project_translations', true);
    }
}
