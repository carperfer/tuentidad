<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Álbumes de fotos y sus fotos. Los archivos se guardan en
 * writable/uploads/photos con el nombre de `filename`.
 */
class CreateAlbumsAndPhotos extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'          => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'user_id'     => ['type' => 'INT', 'unsigned' => true],
            'title'       => ['type' => 'VARCHAR', 'constraint' => 100],
            'description' => ['type' => 'VARCHAR', 'constraint' => 500, 'default' => ''],
            'created_at'  => ['type' => 'DATETIME', 'null' => true],
            'updated_at'  => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('user_id');
        $this->forge->addForeignKey('user_id', 'users', 'id', '', 'CASCADE');
        $this->forge->createTable('albums');

        $this->forge->addField([
            'id'         => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'album_id'   => ['type' => 'INT', 'unsigned' => true],
            'filename'   => ['type' => 'CHAR', 'constraint' => 32],
            'width'      => ['type' => 'SMALLINT', 'unsigned' => true],
            'height'     => ['type' => 'SMALLINT', 'unsigned' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('album_id');
        $this->forge->addUniqueKey('filename');
        $this->forge->addForeignKey('album_id', 'albums', 'id', '', 'CASCADE');
        $this->forge->createTable('photos');
    }

    public function down(): void
    {
        $this->forge->dropTable('photos');
        $this->forge->dropTable('albums');
    }
}
