<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Álbumes de fotos de cada usuario.
 */
class AlbumModel extends Model
{
    protected $table         = 'albums';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $allowedFields = ['user_id', 'title', 'description'];
    protected $dateFormat    = 'datetime';

    /**
     * Álbum con el que empieza cada cuenta, para poder subir fotos sin crear uno antes.
     */
    public const DEFAULT_TITLE = 'Mis fotos';

    public function createDefault(int $userId): int
    {
        return (int) $this->insert(['user_id' => $userId, 'title' => self::DEFAULT_TITLE, 'description' => '']);
    }
}
