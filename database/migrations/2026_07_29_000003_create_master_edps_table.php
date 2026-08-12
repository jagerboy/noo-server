<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Migration untuk tabel master_edps (Akun login EDP Principal berbasis region_code).
     */
    public function up(): void
    {
        Schema::create('master_edps', function (Blueprint $table) {
            $table->id();
            $table->string('region_code', 50)->unique();
            $table->string('password', 100);
            $table->string('nama', 150)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Insert akun default EDP Principal per region & global
        $defaultRegions = ['ALL', 'ASWSUM1', 'ASWSUM2', 'ASWKEPRI', 'ASWBENGKULU', 'ASWJAMBI', 'ASWSUMBAR', 'ASWLAMBABEL', 'ASWRIAU1', 'ASWRIAU2', 'ASWNAD1', 'ASWNAD2'];
        foreach ($defaultRegions as $region) {
            DB::table('master_edps')->insert([
                'region_code' => $region,
                'password' => '123456',
                'nama' => "EDP Principal {$region}",
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('master_edps');
    }
};
