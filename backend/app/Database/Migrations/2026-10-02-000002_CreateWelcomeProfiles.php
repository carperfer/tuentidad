<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Perfiles de bienvenida: dos perfiles de demostración visibles en la portada
 * a los que cualquier visitante puede pedir amistad dejando su email.
 *
 * Son usuarios sin credenciales (no pueden iniciar sesión). Se crean aquí
 * para que existan en todos los entornos tras aplicar las migraciones.
 */
class CreateWelcomeProfiles extends Migration
{
    private const PROFILES = [
        [
            'first_name' => 'Lucía',
            'last_name'  => 'Martín',
            'bio'        => 'Siempre organizando la próxima quedada y subiendo las fotos de la última fiesta.',
            'avatar'     => '/bienvenida/lucia.svg',
        ],
        [
            'first_name' => 'Dani',
            'last_name'  => 'Romero',
            'bio'        => 'Música a todo volumen, partidas los viernes y el plan del finde siempre preparado.',
            'avatar'     => '/bienvenida/dani.svg',
        ],
    ];

    public function up(): void
    {
        $this->forge->addField([
            'id'         => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'user_id'    => ['type' => 'INT', 'unsigned' => true],
            'bio'        => ['type' => 'VARCHAR', 'constraint' => 255],
            'avatar'     => ['type' => 'VARCHAR', 'constraint' => 100],
            'position'   => ['type' => 'TINYINT', 'unsigned' => true, 'default' => 0],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('user_id');
        $this->forge->addForeignKey('user_id', 'users', 'id', '', 'CASCADE');
        $this->forge->createTable('welcome_profiles');

        $now = date('Y-m-d H:i:s');

        foreach (self::PROFILES as $position => $profile) {
            $this->db->table('users')->insert([
                'first_name' => $profile['first_name'],
                'last_name'  => $profile['last_name'],
                'active'     => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $this->db->table('welcome_profiles')->insert([
                'user_id'    => $this->db->insertID(),
                'bio'        => $profile['bio'],
                'avatar'     => $profile['avatar'],
                'position'   => $position,
                'created_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        $userIds = array_column($this->db->table('welcome_profiles')->select('user_id')->get()->getResultArray(), 'user_id');

        $this->forge->dropTable('welcome_profiles');

        if ($userIds !== []) {
            $this->db->table('users')->whereIn('id', $userIds)->delete();
        }
    }
}
