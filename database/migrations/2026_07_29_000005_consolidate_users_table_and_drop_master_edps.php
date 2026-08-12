<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Hash;

return new class extends Migration
{
    /**
     * Consolidate portal authentication to users table & drop master_edps.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'username')) {
                $table->string('username', 100)->nullable()->unique()->after('id');
            }
            // Drop unique constraint on email if present
            try {
                $table->dropUnique('users_email_unique');
            } catch (\Throwable $e) {}
            
            $table->string('email')->nullable()->change();
        });

        // Copy records from master_edps to users if master_edps table exists
        if (Schema::hasTable('master_edps')) {
            $edpAccounts = DB::table('master_edps')->get();
            foreach ($edpAccounts as $acc) {
                $username = strtolower(trim($acc->username ?? $acc->region_code ?? "user_{$acc->id}"));
                $email = "{$username}@noo.portal";

                $password = $acc->password ?? '123456';
                if (!str_starts_with($password, '$2y$')) {
                    $password = Hash::make($password);
                }

                DB::table('users')->updateOrInsert(
                    ['username' => $username],
                    [
                        'name' => $acc->nama ?? "User ({$username})",
                        'email' => $email,
                        'password' => $password,
                        'role' => $acc->role ?? 'EDP_REGION',
                        'region_code' => $acc->region_code,
                        'entity_code_principal' => $acc->entity_code_principal ?? (str_starts_with(strtoupper((string) $acc->region_code), 'INA') ? 'INA' : 'ASW'),
                        'is_active' => $acc->is_active ?? true,
                        'updated_at' => now(),
                    ]
                );
            }

            // Drop master_edps
            Schema::dropIfExists('master_edps');
        }

        // Seed default accounts into users table
        $defaultUsers = [
            [
                'username' => 'superadmin',
                'name' => 'Super Administrator NOO+',
                'email' => 'superadmin@noo.portal',
                'password' => Hash::make('123456'),
                'role' => 'SUPERADMIN',
                'region_code' => null,
                'entity_code_principal' => null,
            ],
            [
                'username' => 'admin.asw',
                'name' => 'Admin Principal ASWFOODS',
                'email' => 'admin.asw@noo.portal',
                'password' => Hash::make('123456'),
                'role' => 'ADMIN_PRINCIPAL',
                'region_code' => 'ASWJAWA',
                'entity_code_principal' => 'ASW',
            ],
            [
                'username' => 'admin.ina',
                'name' => 'Admin Principal INAFOODS',
                'email' => 'admin.ina@noo.portal',
                'password' => Hash::make('123456'),
                'role' => 'ADMIN_PRINCIPAL',
                'region_code' => 'INAJAWA',
                'entity_code_principal' => 'INA',
            ],
        ];

        foreach ($defaultUsers as $uData) {
            DB::table('users')->updateOrInsert(
                ['username' => $uData['username']],
                array_merge($uData, [
                    'is_active' => true,
                    'updated_at' => now(),
                ])
            );
        }

        // Sync PostgreSQL sequence for users table
        try {
            DB::statement("SELECT setval('users_id_seq', COALESCE((SELECT MAX(id) FROM users), 1))");
        } catch (\Throwable $e) {}
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
    }
};
