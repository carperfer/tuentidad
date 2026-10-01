<?php

namespace App\Models;

use CodeIgniter\Model;

class InvitationModel extends Model
{
    protected $table          = 'invitations';
    protected $returnType     = 'array';
    protected $useTimestamps  = true;
    protected $allowedFields  = ['inviter_id', 'email', 'token_hash', 'expires_at', 'accepted_at', 'user_id'];
    protected $dateFormat     = 'datetime';
    protected $useSoftDeletes = false;

    /**
     * Invitación aún utilizable (ni aceptada ni caducada) para un token.
     */
    public function findUsableByToken(string $token): ?array
    {
        return $this->where('token_hash', hash('sha256', $token))
            ->where('accepted_at', null)
            ->where('expires_at >', date('Y-m-d H:i:s'))
            ->first();
    }

    /**
     * Invitación pendiente de un usuario a un email concreto.
     */
    public function findPending(int $inviterId, string $email): ?array
    {
        return $this->where('inviter_id', $inviterId)
            ->where('email', $email)
            ->where('accepted_at', null)
            ->first();
    }

    public function countSentBy(int $inviterId): int
    {
        return $this->where('inviter_id', $inviterId)->countAllResults();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listSentBy(int $inviterId): array
    {
        return $this->select('email, expires_at, accepted_at, created_at')
            ->where('inviter_id', $inviterId)
            ->orderBy('created_at', 'DESC')
            ->orderBy('id', 'DESC')
            ->findAll();
    }
}
