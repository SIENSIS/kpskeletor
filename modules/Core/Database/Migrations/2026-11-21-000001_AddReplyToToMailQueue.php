<?php

declare(strict_types=1);

namespace Modules\Core\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Column `mail_queue.reply_to` (session 48, D-48-03): an optional `Reply-To` address of a queued
 * email (the Contact module's emails to a recipient's addresses answer the visitor). The sender
 * stays the site's address.
 */
final class AddReplyToToMailQueue extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('mail_queue', [
            'reply_to' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true, 'after' => 'to_email'],
        ]);
    }

    public function down(): void
    {
        $this->forge->dropColumn('mail_queue', 'reply_to');
    }
}
