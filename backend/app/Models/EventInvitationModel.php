<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Invitados a un evento y su respuesta: pending, yes, no o maybe.
 */
class EventInvitationModel extends Model
{
    public const STATUSES = ['yes', 'no', 'maybe'];

    protected $table         = 'event_invitations';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $updatedField  = '';
    protected $allowedFields = ['event_id', 'user_id', 'status', 'responded_at'];
    protected $dateFormat    = 'datetime';
}
