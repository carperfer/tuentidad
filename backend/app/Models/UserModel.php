<?php

namespace App\Models;

use CodeIgniter\Shield\Models\UserModel as ShieldUserModel;

/**
 * Usuarios de Shield con los datos personales de tuentidad.
 */
class UserModel extends ShieldUserModel
{
    protected function initialize(): void
    {
        parent::initialize();

        $this->allowedFields = [
            ...$this->allowedFields,
            'first_name',
            'last_name',
            'birthdate',
            'bio',
            'profile_status',
            'profile_status_updated_at',
            'avatar',
            'birthdate_visibility',
            'bio_visibility',
            'status_visibility',
            'avatar_visibility',
        ];
    }
}
