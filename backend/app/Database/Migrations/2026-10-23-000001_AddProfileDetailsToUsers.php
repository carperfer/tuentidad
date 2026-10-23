<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Perfil editable y opciones de privacidad de cada usuario.
 */
class AddProfileDetailsToUsers extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('users', [
            'bio'                  => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true, 'after' => 'birthdate'],
            'profile_status'       => ['type' => 'VARCHAR', 'constraint' => 160, 'null' => true, 'after' => 'bio'],
            'avatar'               => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true, 'after' => 'profile_status'],
            'birthdate_visibility' => ['type' => 'VARCHAR', 'constraint' => 10, 'default' => 'friends', 'after' => 'avatar'],
            'bio_visibility'       => ['type' => 'VARCHAR', 'constraint' => 10, 'default' => 'public', 'after' => 'birthdate_visibility'],
            'status_visibility'    => ['type' => 'VARCHAR', 'constraint' => 10, 'default' => 'public', 'after' => 'bio_visibility'],
            'avatar_visibility'    => ['type' => 'VARCHAR', 'constraint' => 10, 'default' => 'public', 'after' => 'status_visibility'],
        ]);
    }

    public function down(): void
    {
        $this->forge->dropColumn('users', [
            'bio',
            'profile_status',
            'avatar',
            'birthdate_visibility',
            'bio_visibility',
            'status_visibility',
            'avatar_visibility',
        ]);
    }
}
