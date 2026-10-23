<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateFriendRequests extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'           => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'sender_id'    => ['type' => 'INT', 'unsigned' => true],
            'recipient_id' => ['type' => 'INT', 'unsigned' => true],
            'created_at'   => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey(['sender_id', 'recipient_id']);
        $this->forge->addKey('recipient_id');
        $this->forge->addForeignKey('sender_id', 'users', 'id', '', 'CASCADE');
        $this->forge->addForeignKey('recipient_id', 'users', 'id', '', 'CASCADE');
        $this->forge->createTable('friend_requests');
    }

    public function down(): void
    {
        $this->forge->dropTable('friend_requests');
    }
}
