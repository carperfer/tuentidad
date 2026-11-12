<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Comentarios de una foto, en orden de publicación.
 */
class PhotoCommentModel extends Model
{
    protected $table         = 'photo_comments';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $updatedField  = '';
    protected $allowedFields = ['photo_id', 'user_id', 'body'];
    protected $dateFormat    = 'datetime';
}
