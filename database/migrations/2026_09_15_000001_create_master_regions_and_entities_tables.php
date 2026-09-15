<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Tabel Master Regions
        if (!Schema::hasTable('master_regions')) {
            Schema::create('master_regions', function (Blueprint $table) {
                $table->id();
                $table->string('region_code', 50)->unique();
                $table->string('region_name', 100);
                $table->string('principal_code', 20)->default('ASW');
                $table->string('principal_name', 100)->default('ASWFOODS');
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        // 2. Tabel Master Entities
        if (!Schema::hasTable('master_entities')) {
            Schema::create('master_entities', function (Blueprint $table) {
                $table->id();
                $table->string('region_code', 50);
                $table->string('region_name', 100)->nullable();
                $table->string('entity_code_principal', 50)->unique();
                $table->string('entity_name_principal', 150);
                $table->string('principal_code', 20)->default('ASW');
                $table->string('principal_name', 100)->default('ASWFOODS');
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index('region_code');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('master_entities');
        Schema::dropIfExists('master_regions');
    }
};
