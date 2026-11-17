<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Mensajes del chat entre amigos (conversaciones 1 a 1).
 */
class CreateMessages extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'           => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'sender_id'    => ['type' => 'INT', 'unsigned' => true],
            'recipient_id' => ['type' => 'INT', 'unsigned' => true],
            'body'         => ['type' => 'VARCHAR', 'constraint' => 1000],
            'created_at'   => ['type' => 'DATETIME', 'null' => true],
            'read_at'      => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey(['sender_id', 'recipient_id', 'id']);
        $this->forge->addKey(['recipient_id', 'read_at']);
        $this->forge->addForeignKey('sender_id', 'users', 'id', '', 'CASCADE');
        $this->forge->addForeignKey('recipient_id', 'users', 'id', '', 'CASCADE');
        $this->forge->createTable('messages');
    }

    public function down(): void
    {
        $this->forge->dropTable('messages');
    }
}
