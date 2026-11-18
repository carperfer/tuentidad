<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Emails de resumen opcionales (desactivados por defecto) e índice para
 * paginar los avisos de cada usuario por orden de llegada.
 */
class AddDigestsAndNotificationIndex extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('users', [
            // none, daily o weekly
            'digest'         => ['type' => 'VARCHAR', 'constraint' => 10, 'default' => 'none', 'after' => 'avatar_visibility'],
            'digest_sent_at' => ['type' => 'DATETIME', 'null' => true, 'after' => 'digest'],
        ]);
        $this->db->query('CREATE INDEX notifications_user_id_id ON notifications (user_id, id)');
    }

    public function down(): void
    {
        $this->db->query('DROP INDEX notifications_user_id_id ON notifications');
        $this->forge->dropColumn('users', ['digest', 'digest_sent_at']);
    }
}
