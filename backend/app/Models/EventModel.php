<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Eventos; starts_at está en UTC.
 */
class EventModel extends Model
{
    protected $table         = 'events';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $allowedFields = ['user_id', 'title', 'place', 'description', 'starts_at', 'reminded_at'];
    protected $dateFormat    = 'datetime';
}
