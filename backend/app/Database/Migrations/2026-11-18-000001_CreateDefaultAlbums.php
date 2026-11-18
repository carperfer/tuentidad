<?php

namespace App\Database\Migrations;

use App\Models\AlbumModel;
use CodeIgniter\Database\Migration;

/**
 * Las cuentas nuevas empiezan con el álbum «Mis fotos». Se crea también para
 * las que ya existían sin ningún álbum (no para los perfiles de bienvenida,
 * que no tienen cuenta).
 */
class CreateDefaultAlbums extends Migration
{
    public function up(): void
    {
        $now   = date('Y-m-d H:i:s');
        $users = $this->db->table('users')
            ->select('users.id')
            ->join('albums', 'albums.user_id = users.id', 'left')
            ->join('welcome_profiles', 'welcome_profiles.user_id = users.id', 'left')
            ->where('albums.id', null)
            ->where('welcome_profiles.user_id', null)
            ->get()
            ->getResultArray();

        foreach ($users as $user) {
            $this->db->table('albums')->insert([
                'user_id'     => $user['id'],
                'title'       => AlbumModel::DEFAULT_TITLE,
                'description' => '',
                'created_at'  => $now,
                'updated_at'  => $now,
            ]);
        }
    }

    public function down(): void
    {
        // Los álbumes pueden tener ya fotos: no se borran
    }
}
