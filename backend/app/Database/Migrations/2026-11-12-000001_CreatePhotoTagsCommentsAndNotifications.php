<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Etiquetas y comentarios de las fotos y avisos en la web.
 * Cada aviso apunta a lo que lo ha generado (etiqueta, comentario o solicitud
 * de amistad) y se borra en cascada cuando eso desaparece.
 */
class CreatePhotoTagsCommentsAndNotifications extends Migration
{
    public function up(): void
    {
        // Zona etiquetada en porcentajes de la imagen, para que no dependa del tamaño mostrado
        $this->forge->addField([
            'id'         => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'photo_id'   => ['type' => 'INT', 'unsigned' => true],
            'user_id'    => ['type' => 'INT', 'unsigned' => true],
            'tagger_id'  => ['type' => 'INT', 'unsigned' => true],
            'x'          => ['type' => 'DECIMAL', 'constraint' => '5,2'],
            'y'          => ['type' => 'DECIMAL', 'constraint' => '5,2'],
            'width'      => ['type' => 'DECIMAL', 'constraint' => '5,2'],
            'height'     => ['type' => 'DECIMAL', 'constraint' => '5,2'],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey(['photo_id', 'user_id']);
        $this->forge->addKey('user_id');
        $this->forge->addForeignKey('photo_id', 'photos', 'id', '', 'CASCADE');
        $this->forge->addForeignKey('user_id', 'users', 'id', '', 'CASCADE');
        $this->forge->addForeignKey('tagger_id', 'users', 'id', '', 'CASCADE');
        $this->forge->createTable('photo_tags');

        $this->forge->addField([
            'id'         => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'photo_id'   => ['type' => 'INT', 'unsigned' => true],
            'user_id'    => ['type' => 'INT', 'unsigned' => true],
            'body'       => ['type' => 'VARCHAR', 'constraint' => 500],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('photo_id');
        $this->forge->addForeignKey('photo_id', 'photos', 'id', '', 'CASCADE');
        $this->forge->addForeignKey('user_id', 'users', 'id', '', 'CASCADE');
        $this->forge->createTable('photo_comments');

        $this->forge->addField([
            'id'                => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'user_id'           => ['type' => 'INT', 'unsigned' => true],
            'actor_id'          => ['type' => 'INT', 'unsigned' => true],
            'type'              => ['type' => 'VARCHAR', 'constraint' => 20],
            'photo_id'          => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'photo_tag_id'      => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'photo_comment_id'  => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'friend_request_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'created_at'        => ['type' => 'DATETIME', 'null' => true],
            'read_at'           => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey(['user_id', 'read_at']);
        $this->forge->addForeignKey('user_id', 'users', 'id', '', 'CASCADE');
        $this->forge->addForeignKey('actor_id', 'users', 'id', '', 'CASCADE');
        $this->forge->addForeignKey('photo_id', 'photos', 'id', '', 'CASCADE');
        $this->forge->addForeignKey('photo_tag_id', 'photo_tags', 'id', '', 'CASCADE');
        $this->forge->addForeignKey('photo_comment_id', 'photo_comments', 'id', '', 'CASCADE');
        $this->forge->addForeignKey('friend_request_id', 'friend_requests', 'id', '', 'CASCADE');
        $this->forge->createTable('notifications');
    }

    public function down(): void
    {
        $this->forge->dropTable('notifications');
        $this->forge->dropTable('photo_comments');
        $this->forge->dropTable('photo_tags');
    }
}
