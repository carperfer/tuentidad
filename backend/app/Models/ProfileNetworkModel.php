<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * «Redes» del perfil: estudios universitarios, trayectoria profesional y
 * colegios, con sus años (end_year null: hasta ahora).
 */
class ProfileNetworkModel extends Model
{
    public const KINDS = ['university', 'work', 'school'];

    public const MAX_ITEMS = 20;

    protected $table         = 'profile_networks';
    protected $returnType    = 'array';
    protected $allowedFields = ['user_id', 'kind', 'name', 'detail', 'start_year', 'end_year', 'position'];
}
