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
        Schema::table('noo_submissions', function (Blueprint $table) {
            if (!Schema::hasColumn('noo_submissions', 'previous_code_noo_principal')) {
                $table->string('previous_code_noo_principal', 50)->nullable()->after('code_noo_principal');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('noo_submissions', function (Blueprint $table) {
            if (Schema::hasColumn('noo_submissions', 'previous_code_noo_principal')) {
                $table->dropColumn('previous_code_noo_principal');
            }
        });
    }
};
