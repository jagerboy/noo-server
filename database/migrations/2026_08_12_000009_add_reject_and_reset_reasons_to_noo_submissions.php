<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Jalankan penambahan kolom reject_reason dan reset_reason.
     */
    public function up(): void
    {
        Schema::table('noo_submissions', function (Blueprint $table) {
            if (!Schema::hasColumn('noo_submissions', 'reject_reason')) {
                $table->text('reject_reason')->nullable()->after('spv_notes');
            }
            if (!Schema::hasColumn('noo_submissions', 'reset_reason')) {
                $table->text('reset_reason')->nullable()->after('reject_reason');
            }
        });
    }

    /**
     * Batalkan pembuatan kolom.
     */
    public function down(): void
    {
        Schema::table('noo_submissions', function (Blueprint $table) {
            if (Schema::hasColumn('noo_submissions', 'reset_reason')) {
                $table->dropColumn('reset_reason');
            }
            if (Schema::hasColumn('noo_submissions', 'reject_reason')) {
                $table->dropColumn('reject_reason');
            }
        });
    }
};
