<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Amistades confirmadas. Cada amistad se guarda en los dos sentidos
 * (A→B y B→A) para consultar los amigos de alguien con un solo índice.
 */
class CreateFriendships extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'         => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'user_id'    => ['type' => 'INT', 'unsigned' => true],
            'friend_id'  => ['type' => 'INT', 'unsigned' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey(['user_id', 'friend_id']);
        $this->forge->addForeignKey('user_id', 'users', 'id', '', 'CASCADE');
        $this->forge->addForeignKey('friend_id', 'users', 'id', '', 'CASCADE');
        $this->forge->createTable('friendships');
    }

    public function down(): void
    {
        $this->forge->dropTable('friendships');
    }
}
