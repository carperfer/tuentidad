<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Momento en que la persona invitada aceptó la política de privacidad al
 * pedir la invitación ella misma (solicitudes a los perfiles de bienvenida).
 */
class AddConsentToInvitations extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('invitations', [
            'consented_at' => ['type' => 'DATETIME', 'null' => true, 'after' => 'user_id'],
        ]);
    }

    public function down(): void
    {
        $this->forge->dropColumn('invitations', 'consented_at');
    }
}
