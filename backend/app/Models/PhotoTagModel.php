<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Personas etiquetadas en una foto y la zona que ocupan (en % de la imagen).
 */
class PhotoTagModel extends Model
{
    protected $table         = 'photo_tags';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $updatedField  = '';
    protected $allowedFields = ['photo_id', 'user_id', 'tagger_id', 'x', 'y', 'width', 'height'];
    protected $dateFormat    = 'datetime';
}
