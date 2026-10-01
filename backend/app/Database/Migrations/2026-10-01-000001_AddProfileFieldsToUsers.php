<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Datos personales de los usuarios, sobre la tabla users de Shield.
 */
class AddProfileFieldsToUsers extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('users', [
            'first_name' => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true, 'after' => 'username'],
            'last_name'  => ['type' => 'VARCHAR', 'constraint' => 80, 'null' => true, 'after' => 'first_name'],
            'birthdate'  => ['type' => 'DATE', 'null' => true, 'after' => 'last_name'],
        ]);
    }

    public function down(): void
    {
        $this->forge->dropColumn('users', ['first_name', 'last_name', 'birthdate']);
    }
}
