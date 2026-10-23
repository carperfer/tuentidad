<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Perfiles de bienvenida (de demostración) que se muestran en la portada.
 */
class WelcomeProfileModel extends Model
{
    protected $table      = 'welcome_profiles';
    protected $returnType = 'array';

    /**
     * @return list<array{id: int, first_name: string, last_name: string, bio: string, avatar: string}>
     */
    public function listPublic(): array
    {
        $rows = $this->select('welcome_profiles.id, users.first_name, users.last_name, welcome_profiles.bio, welcome_profiles.avatar')
            ->join('users', 'users.id = welcome_profiles.user_id')
            ->orderBy('welcome_profiles.position')
            ->findAll();

        return array_map(static fn (array $row): array => ['id' => (int) $row['id']] + $row, $rows);
    }

    public function isWelcomeUser(int $userId): bool
    {
        return $this->where('user_id', $userId)->countAllResults() > 0;
    }
}
