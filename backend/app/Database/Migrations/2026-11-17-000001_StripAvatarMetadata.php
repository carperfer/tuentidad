<?php

namespace App\Database\Migrations;

use App\Libraries\PhotoProcessor;
use CodeIgniter\Database\Migration;
use InvalidArgumentException;

/**
 * Las fotos de perfil subidas antes del Sprint 8 se guardaban tal cual, con
 * sus metadatos EXIF (que pueden incluir la ubicación). Se vuelven a generar
 * en JPEG como las nuevas. No tiene vuelta atrás: los originales se borran.
 */
class StripAvatarMetadata extends Migration
{
    public function up(): void
    {
        $directory = WRITEPATH . 'uploads/avatars';
        $processor = new PhotoProcessor($directory);
        $users     = $this->db->table('users')->select('id, avatar')->where('avatar IS NOT NULL', null, false)->where('avatar !=', '')->get()->getResultArray();

        foreach ($users as $user) {
            $old = (string) $user['avatar'];
            if (! preg_match('/\A[a-f0-9]{32}\.(jpg|png|webp)\z/', $old) || ! is_file("{$directory}/{$old}")) {
                continue;
            }

            try {
                $new = $processor->processSingle("{$directory}/{$old}", 600);
            } catch (InvalidArgumentException) {
                // Si no se puede leer, se deja como está
                continue;
            }

            $this->db->table('users')->where('id', $user['id'])->update(['avatar' => $new]);
            unlink("{$directory}/{$old}");
        }
    }

    public function down(): void
    {
    }
}
