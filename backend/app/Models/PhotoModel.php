<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Fotos de un álbum, en el orden en que se subieron.
 */
class PhotoModel extends Model
{
    protected $table         = 'photos';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $updatedField  = '';
    protected $allowedFields = ['album_id', 'filename', 'width', 'height'];
    protected $dateFormat    = 'datetime';
}
