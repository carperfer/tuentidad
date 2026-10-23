<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddProfileStatusTimestamp extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('users', [
            'profile_status_updated_at' => ['type' => 'DATETIME', 'null' => true, 'after' => 'profile_status'],
        ]);
    }

    public function down(): void
    {
        $this->forge->dropColumn('users', 'profile_status_updated_at');
    }
}
