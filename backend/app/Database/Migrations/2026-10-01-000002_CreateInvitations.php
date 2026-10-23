<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Invitaciones: la única forma de entrar en tuentidad.
 *
 * El token se envía por email y aquí solo se guarda su hash SHA-256.
 */
class CreateInvitations extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'          => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'inviter_id'  => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'email'       => ['type' => 'VARCHAR', 'constraint' => 254],
            'token_hash'  => ['type' => 'CHAR', 'constraint' => 64],
            'expires_at'  => ['type' => 'DATETIME'],
            'accepted_at' => ['type' => 'DATETIME', 'null' => true],
            'user_id'     => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'created_at'  => ['type' => 'DATETIME', 'null' => true],
            'updated_at'  => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('token_hash');
        $this->forge->addKey('email');
        $this->forge->addForeignKey('inviter_id', 'users', 'id', '', 'SET NULL');
        $this->forge->addForeignKey('user_id', 'users', 'id', '', 'SET NULL');
        $this->forge->createTable('invitations');
    }

    public function down(): void
    {
        $this->forge->dropTable('invitations');
    }
}
