<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Hash;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('master_edps', function (Blueprint $table) {
            $table->string('username', 100)->nullable()->unique()->after('id');
            $table->string('role', 30)->default('EDP_REGION')->after('username');
            $table->string('entity_code_principal', 50)->nullable()->after('role');
            $table->string('region_code', 50)->nullable()->change();
        });

        // Populate default accounts
        // 1. SUPERADMIN
        DB::table('master_edps')->insert([
            'username' => 'superadmin',
            'password' => '123456',
            'nama' => 'Super Administrator NOO+',
            'role' => 'SUPERADMIN',
            'region_code' => null,
            'entity_code_principal' => null,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 2. ADMIN PRINCIPAL (contoh ASW & INA)
        DB::table('master_edps')->insert([
            'username' => 'admin.asw',
            'password' => '123456',
            'nama' => 'Admin Principal ASWFOODS',
            'role' => 'ADMIN_PRINCIPAL',
            'region_code' => 'ASWJAWA',
            'entity_code_principal' => 'ASW',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('master_edps')->insert([
            'username' => 'admin.ina',
            'password' => '123456',
            'nama' => 'Admin Principal INAFOODS',
            'role' => 'ADMIN_PRINCIPAL',
            'region_code' => 'INAJAWA',
            'entity_code_principal' => 'INA',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Populate usernames for existing EDP region records
        $existingEdps = DB::table('master_edps')->whereNull('username')->get();
        foreach ($existingEdps as $edp) {
            $regLower = strtolower(trim($edp->region_code));
            $username = "edp.{$regLower}";
            DB::table('master_edps')->where('id', $edp->id)->update([
                'username' => $username,
                'role' => 'EDP_REGION',
                'entity_code_principal' => str_starts_with(strtoupper($edp->region_code), 'INA') ? 'INA' : 'ASW',
            ]);
        }

        // Tabel Audit Activity Logs
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->string('username', 100);
            $table->string('user_role', 50);
            $table->string('action', 100);
            $table->string('module', 100);
            $table->text('description');
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('activity_logs');

        Schema::table('master_edps', function (Blueprint $table) {
            $table->dropColumn(['username', 'role', 'entity_code_principal']);
        });
    }
};
