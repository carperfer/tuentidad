<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Mensajes del chat, en orden de envío.
 */
class MessageModel extends Model
{
    protected $table         = 'messages';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $updatedField  = '';
    protected $allowedFields = ['sender_id', 'recipient_id', 'body', 'read_at'];
    protected $dateFormat    = 'datetime';
}
