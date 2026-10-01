<?php

namespace App\Models;

use CodeIgniter\Model;

class PasswordResetModel extends Model
{
    protected $table         = 'password_resets';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $updatedField  = '';
    protected $allowedFields = ['user_id', 'token_hash', 'expires_at', 'used_at'];
    protected $dateFormat    = 'datetime';

    public function findUsableByToken(string $token): ?array
    {
        return $this->where('token_hash', hash('sha256', $token))
            ->where('used_at', null)
            ->where('expires_at >', date('Y-m-d H:i:s'))
            ->first();
    }

    /**
     * Invalida los enlaces pendientes de un usuario.
     */
    public function invalidateFor(int $userId): void
    {
        $this->where('user_id', $userId)
            ->where('used_at', null)
            ->set('used_at', date('Y-m-d H:i:s'))
            ->update();
    }
}
