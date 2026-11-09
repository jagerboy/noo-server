<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Update unique constraint on master_salesmen table to allow the same salesman_code across different branches (branch_id).
     */
    public function up(): void
    {
        Schema::table('master_salesmen', function (Blueprint $table) {
            $table->dropUnique('master_salesmen_salesman_code_unique');
            $table->unique(['salesman_code', 'branch_id'], 'master_salesmen_branch_salesman_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('master_salesmen', function (Blueprint $table) {
            $table->dropUnique('master_salesmen_branch_salesman_unique');
            $table->unique('salesman_code', 'master_salesmen_salesman_code_unique');
        });
    }
};

