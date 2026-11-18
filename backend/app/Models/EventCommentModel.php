<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Comentarios de un evento, en orden de publicación.
 */
class EventCommentModel extends Model
{
    protected $table         = 'event_comments';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $updatedField  = '';
    protected $allowedFields = ['event_id', 'user_id', 'body'];
    protected $dateFormat    = 'datetime';
}
