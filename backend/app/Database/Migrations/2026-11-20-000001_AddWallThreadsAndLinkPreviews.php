<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Tablón con hilos de respuestas y tarjetas de los enlaces (título, resumen
 * y foto que se leen de la propia página).
 */
class AddWallThreadsAndLinkPreviews extends Migration
{
    public function up(): void
    {
        // Caché compartida: el mismo enlace se lee una sola vez
        $this->forge->addField([
            'id'          => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'url_hash'    => ['type' => 'CHAR', 'constraint' => 64],
            'url'         => ['type' => 'VARCHAR', 'constraint' => 2048],
            'title'       => ['type' => 'VARCHAR', 'constraint' => 200],
            'description' => ['type' => 'VARCHAR', 'constraint' => 300, 'default' => ''],
            'site_name'   => ['type' => 'VARCHAR', 'constraint' => 100, 'default' => ''],
            // Foto reducida guardada en writable/uploads/link-previews
            'image'      => ['type' => 'VARCHAR', 'constraint' => 40, 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('url_hash');
        $this->forge->createTable('link_previews');

        $this->forge->addColumn('wall_posts', [
            // Mensaje al que responde (null: mensaje principal)
            'parent_id'       => ['type' => 'INT', 'unsigned' => true, 'null' => true, 'after' => 'author_id'],
            'link_preview_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true, 'after' => 'body'],
        ]);
        $this->db->query('ALTER TABLE wall_posts ADD INDEX wall_posts_parent_id_id (parent_id, id)');
        $this->db->query('ALTER TABLE wall_posts ADD CONSTRAINT wall_posts_parent_id_foreign FOREIGN KEY (parent_id) REFERENCES wall_posts (id) ON DELETE CASCADE');
        $this->db->query('ALTER TABLE wall_posts ADD CONSTRAINT wall_posts_link_preview_id_foreign FOREIGN KEY (link_preview_id) REFERENCES link_previews (id) ON DELETE SET NULL');
    }

    public function down(): void
    {
        $this->forge->dropForeignKey('wall_posts', 'wall_posts_link_preview_id_foreign');
        $this->forge->dropForeignKey('wall_posts', 'wall_posts_parent_id_foreign');
        $this->db->query('ALTER TABLE wall_posts DROP INDEX wall_posts_parent_id_id');
        $this->forge->dropColumn('wall_posts', ['parent_id', 'link_preview_id']);
        $this->forge->dropTable('link_previews');
    }
}
