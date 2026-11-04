<?php

declare(strict_types=1);

namespace Modules\Core\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Marks the modules whose `defaultGrants` have been applied (only once, on first install).
 */
final class AddGrantsAppliedToModules extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('modules', [
            'grants_applied' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0, 'after' => 'enabled'],
        ]);
    }

    public function down(): void
    {
        $this->forge->dropColumn('modules', 'grants_applied');
    }
}
