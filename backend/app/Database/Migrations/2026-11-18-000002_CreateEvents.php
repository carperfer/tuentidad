<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Eventos (cumpleaños, fiestas, quedadas): solo los ven quien los organiza y
 * sus invitados. Los avisos pueden apuntar a un evento o a un comentario suyo.
 */
class CreateEvents extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'          => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'user_id'     => ['type' => 'INT', 'unsigned' => true],
            'title'       => ['type' => 'VARCHAR', 'constraint' => 100],
            'place'       => ['type' => 'VARCHAR', 'constraint' => 150, 'default' => ''],
            'description' => ['type' => 'VARCHAR', 'constraint' => 2000, 'default' => ''],
            // En UTC; la SPA lo muestra en la hora local
            'starts_at'   => ['type' => 'DATETIME'],
            'reminded_at' => ['type' => 'DATETIME', 'null' => true],
            'created_at'  => ['type' => 'DATETIME', 'null' => true],
            'updated_at'  => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('user_id');
        $this->forge->addKey('starts_at');
        $this->forge->addForeignKey('user_id', 'users', 'id', '', 'CASCADE');
        $this->forge->createTable('events');

        // Quien organiza no tiene fila: va siempre
        $this->forge->addField([
            'id'           => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'event_id'     => ['type' => 'INT', 'unsigned' => true],
            'user_id'      => ['type' => 'INT', 'unsigned' => true],
            'status'       => ['type' => 'VARCHAR', 'constraint' => 10, 'default' => 'pending'],
            'created_at'   => ['type' => 'DATETIME', 'null' => true],
            'responded_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey(['event_id', 'user_id']);
        $this->forge->addKey('user_id');
        $this->forge->addForeignKey('event_id', 'events', 'id', '', 'CASCADE');
        $this->forge->addForeignKey('user_id', 'users', 'id', '', 'CASCADE');
        $this->forge->createTable('event_invitations');

        $this->forge->addField([
            'id'         => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'event_id'   => ['type' => 'INT', 'unsigned' => true],
            'user_id'    => ['type' => 'INT', 'unsigned' => true],
            'body'       => ['type' => 'VARCHAR', 'constraint' => 500],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('event_id');
        $this->forge->addForeignKey('event_id', 'events', 'id', '', 'CASCADE');
        $this->forge->addForeignKey('user_id', 'users', 'id', '', 'CASCADE');
        $this->forge->createTable('event_comments');

        $this->forge->addColumn('notifications', [
            'event_id'         => ['type' => 'INT', 'unsigned' => true, 'null' => true, 'after' => 'friend_request_id'],
            'event_comment_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true, 'after' => 'event_id'],
        ]);
        $this->db->query('ALTER TABLE notifications ADD CONSTRAINT notifications_event_id_foreign FOREIGN KEY (event_id) REFERENCES events (id) ON DELETE CASCADE');
        $this->db->query('ALTER TABLE notifications ADD CONSTRAINT notifications_event_comment_id_foreign FOREIGN KEY (event_comment_id) REFERENCES event_comments (id) ON DELETE CASCADE');
    }

    public function down(): void
    {
        $this->forge->dropForeignKey('notifications', 'notifications_event_comment_id_foreign');
        $this->forge->dropForeignKey('notifications', 'notifications_event_id_foreign');
        $this->forge->dropColumn('notifications', ['event_id', 'event_comment_id']);
        $this->forge->dropTable('event_comments');
        $this->forge->dropTable('event_invitations');
        $this->forge->dropTable('events');
    }
}
