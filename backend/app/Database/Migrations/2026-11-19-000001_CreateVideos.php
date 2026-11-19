<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Vídeos compartidos: enlaces de YouTube (solo se guarda el id del vídeo).
 */
class CreateVideos extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'         => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'user_id'    => ['type' => 'INT', 'unsigned' => true],
            'youtube_id' => ['type' => 'CHAR', 'constraint' => 11],
            'title'      => ['type' => 'VARCHAR', 'constraint' => 150],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey(['user_id', 'id']);
        $this->forge->addForeignKey('user_id', 'users', 'id', '', 'CASCADE');
        $this->forge->createTable('videos');
    }

    public function down(): void
    {
        $this->forge->dropTable('videos');
    }
}
