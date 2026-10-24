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
}
