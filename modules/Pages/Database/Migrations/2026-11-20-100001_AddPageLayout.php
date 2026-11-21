<?php

declare(strict_types=1);

namespace Modules\Pages\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Column `pages.layout` (D-45-01): `article` (the page shows its rich-text body, as before) or
 * `sections` (an ordered list of sections, `page_sections`). Switching keeps the other content.
 */
final class AddPageLayout extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('pages', [
            'layout' => ['type' => 'VARCHAR', 'constraint' => 10, 'default' => 'article', 'after' => 'template'],
        ]);
    }

    public function down(): void
    {
        $this->forge->dropColumn('pages', 'layout');
    }
}
