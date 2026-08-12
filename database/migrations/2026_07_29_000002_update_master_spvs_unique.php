<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Update unique constraint on master_spvs table to allow one SPV (salescode) to cover multiple branches (branch_id).
     */
    public function up(): void
    {
        Schema::table('master_spvs', function (Blueprint $table) {
            $table->dropUnique('master_spvs_salescode_unique');
            $table->unique(['salescode', 'branch_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('master_spvs', function (Blueprint $table) {
            $table->dropUnique(['salescode', 'branch_id']);
            $table->unique('salescode');
        });
    }
};
