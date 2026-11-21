<?php

declare(strict_types=1);

namespace Modules\News\Database\Migrations;

use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Database\Migration;

/**
 * The optional banner of a category (session 46; docs/news.md, "Category banners"): the key of an
 * image of the Storage service (public disk, folder `modules/news`), shown as a hero on the
 * category's page and on its articles.
 */
final class AddBannerToNewsCategories extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('news_categories', [
            'banner' => ['type' => 'VARCHAR', 'constraint' => 32, 'null' => true],
        ]);
    }

    public function down(): void
    {
        // The table may already be gone (a down() of its creating migration ran first).
        $db = $this->db;

        if (! $db instanceof BaseConnection || ! $db->tableExists('news_categories')) {
            return;
        }

        $db->resetDataCache();

        if ($db->fieldExists('banner', 'news_categories')) {
            $this->forge->dropColumn('news_categories', 'banner');
        }
    }
}
