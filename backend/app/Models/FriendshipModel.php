<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Amistades confirmadas, guardadas en los dos sentidos.
 */
class FriendshipModel extends Model
{
    protected $table         = 'friendships';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $updatedField  = '';
    protected $allowedFields = ['user_id', 'friend_id'];
    protected $dateFormat    = 'datetime';

    /**
     * Hace amigos a dos usuarios (no hace nada si ya lo son).
     */
    public function befriend(int $userId, int $friendId): void
    {
        $now = date('Y-m-d H:i:s');

        foreach ([[$userId, $friendId], [$friendId, $userId]] as [$a, $b]) {
            $this->db->table($this->table)->ignore(true)->insert([
                'user_id'    => $a,
                'friend_id'  => $b,
                'created_at' => $now,
            ]);
        }
    }

    public function areFriends(int $userId, int $friendId): bool
    {
        return $this->where('user_id', $userId)->where('friend_id', $friendId)->countAllResults() > 0;
    }
}
