<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Perfil completo: «Redes» (estudios, trabajos y colegios con sus años, y
 * enlaces a redes sociales, cada apartado con su privacidad) y tablón.
 */
class CreateProfileNetworksLinksAndWall extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'         => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'user_id'    => ['type' => 'INT', 'unsigned' => true],
            // university, work o school
            'kind'       => ['type' => 'VARCHAR', 'constraint' => 12],
            'name'       => ['type' => 'VARCHAR', 'constraint' => 120],
            'detail'     => ['type' => 'VARCHAR', 'constraint' => 150, 'default' => ''],
            'start_year' => ['type' => 'SMALLINT', 'unsigned' => true, 'null' => true],
            // null: hasta ahora
            'end_year'   => ['type' => 'SMALLINT', 'unsigned' => true, 'null' => true],
            'position'   => ['type' => 'TINYINT', 'unsigned' => true, 'default' => 0],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('user_id');
        $this->forge->addForeignKey('user_id', 'users', 'id', '', 'CASCADE');
        $this->forge->createTable('profile_networks');

        $this->forge->addField([
            'id'       => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'user_id'  => ['type' => 'INT', 'unsigned' => true],
            'url'      => ['type' => 'VARCHAR', 'constraint' => 255],
            'position' => ['type' => 'TINYINT', 'unsigned' => true, 'default' => 0],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('user_id');
        $this->forge->addForeignKey('user_id', 'users', 'id', '', 'CASCADE');
        $this->forge->createTable('profile_links');

        $this->forge->addField([
            'id'         => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            // Dueño del tablón
            'profile_id' => ['type' => 'INT', 'unsigned' => true],
            'author_id'  => ['type' => 'INT', 'unsigned' => true],
            'body'       => ['type' => 'VARCHAR', 'constraint' => 1000],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey(['profile_id', 'id']);
        $this->forge->addKey('author_id');
        $this->forge->addForeignKey('profile_id', 'users', 'id', '', 'CASCADE');
        $this->forge->addForeignKey('author_id', 'users', 'id', '', 'CASCADE');
        $this->forge->createTable('wall_posts');

        $this->forge->addColumn('users', [
            'networks_visibility' => ['type' => 'VARCHAR', 'constraint' => 10, 'default' => 'friends', 'after' => 'avatar_visibility'],
            'links_visibility'    => ['type' => 'VARCHAR', 'constraint' => 10, 'default' => 'friends', 'after' => 'networks_visibility'],
        ]);
        $this->forge->addColumn('notifications', [
            'wall_post_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true, 'after' => 'event_comment_id'],
        ]);
        $this->db->query('ALTER TABLE notifications ADD CONSTRAINT notifications_wall_post_id_foreign FOREIGN KEY (wall_post_id) REFERENCES wall_posts (id) ON DELETE CASCADE');
    }

    public function down(): void
    {
        $this->forge->dropForeignKey('notifications', 'notifications_wall_post_id_foreign');
        $this->forge->dropColumn('notifications', 'wall_post_id');
        $this->forge->dropColumn('users', ['networks_visibility', 'links_visibility']);
        $this->forge->dropTable('wall_posts');
        $this->forge->dropTable('profile_links');
        $this->forge->dropTable('profile_networks');
    }
}
