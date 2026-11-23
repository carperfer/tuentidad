<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Mensajes del tablón de cada perfil y sus respuestas (un nivel).
 */
class WallPostModel extends Model
{
    protected $table         = 'wall_posts';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $updatedField  = '';
    protected $allowedFields = ['profile_id', 'author_id', 'parent_id', 'body', 'link_preview_id'];
    protected $dateFormat    = 'datetime';
}
