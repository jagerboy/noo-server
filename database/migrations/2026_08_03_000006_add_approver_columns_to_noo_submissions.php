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
            if (!Schema::hasColumn('noo_submissions', 'approved_by_admin')) {
                $table->string('approved_by_admin', 100)->nullable()->after('admin_notes');
            }
            if (!Schema::hasColumn('noo_submissions', 'approved_by_edp')) {
                $table->string('approved_by_edp', 100)->nullable()->after('edp_notes');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('noo_submissions', function (Blueprint $table) {
            if (Schema::hasColumn('noo_submissions', 'approved_by_admin')) {
                $table->dropColumn('approved_by_admin');
            }
            if (Schema::hasColumn('noo_submissions', 'approved_by_edp')) {
                $table->dropColumn('approved_by_edp');
            }
        });
    }
};
