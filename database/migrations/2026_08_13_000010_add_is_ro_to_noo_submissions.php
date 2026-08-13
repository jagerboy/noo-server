<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Migration penambahan kolom is_ro (Registered Outlet Status) ke tabel noo_submissions.
 * Digunakan untuk mengontrol status keaktifan RO per toko NOO yang telah disetujui EDP / Principal.
 */
return new class extends Migration
{
    /**
     * Jalankan proses penambahan kolom is_ro.
     */
    public function up(): void
    {
        Schema::table('noo_submissions', function (Blueprint $table) {
            if (!Schema::hasColumn('noo_submissions', 'is_ro')) {
                $table->boolean('is_ro')->default(true)->after('status');
            }
        });

        // Set default is_ro = true untuk semua toko yang sudah diapprove
        DB::table('noo_submissions')
            ->whereIn('status', ['APPROVED_EDP', 'EDP_APPROVED', 'INJECTED'])
            ->update(['is_ro' => true]);
    }

    /**
     * Batalkan penambahan kolom is_ro.
     */
    public function down(): void
    {
        Schema::table('noo_submissions', function (Blueprint $table) {
            if (Schema::hasColumn('noo_submissions', 'is_ro')) {
                $table->dropColumn('is_ro');
            }
        });
    }
};
