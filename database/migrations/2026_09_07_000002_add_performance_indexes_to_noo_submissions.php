<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Migrasi untuk menambahkan composite & partial index pada tabel noo_submissions
 * dan activity_logs untuk meningkatkan performa kueri pada server produksi.
 */
return new class extends Migration
{
    /**
     * Jalankan migrasi pembuatan index.
     */
    public function up(): void
    {
        // 1. Composite index untuk pemfilteran inbox cabang & status
        DB::statement('CREATE INDEX IF NOT EXISTS idx_noo_status_branch ON noo_submissions (branch_id, status)');

        // 2. Composite index untuk pemfilteran region & tanggal approval EDP (Monitoring RO)
        DB::statement('CREATE INDEX IF NOT EXISTS idx_noo_region_edp_reviewed ON noo_submissions (region_code, edp_reviewed_at)');

        // 3. Composite index untuk pemfilteran realisasi RO salesman
        DB::statement('CREATE INDEX IF NOT EXISTS idx_noo_salesman_is_ro ON noo_submissions (salesman_code, is_ro)');

        // 4. Index sorting submitted_at
        DB::statement('CREATE INDEX IF NOT EXISTS idx_noo_submitted_at ON noo_submissions (submitted_at DESC)');

        // 5. Partial index untuk antrean yang sudah masuk ke Principal / EDP
        DB::statement('CREATE INDEX IF NOT EXISTS idx_noo_pushed_edp ON noo_submissions (pushed_to_edp_at) WHERE pushed_to_edp_at IS NOT NULL');

        // 6. Index audit log sorting
        if (Schema::hasTable('activity_logs')) {
            DB::statement('CREATE INDEX IF NOT EXISTS idx_activity_logs_created_at ON activity_logs (created_at DESC)');
        }
    }

    /**
     * Rollback migrasi.
     */
    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS idx_noo_status_branch');
        DB::statement('DROP INDEX IF EXISTS idx_noo_region_edp_reviewed');
        DB::statement('DROP INDEX IF EXISTS idx_noo_salesman_is_ro');
        DB::statement('DROP INDEX IF EXISTS idx_noo_submitted_at');
        DB::statement('DROP INDEX IF EXISTS idx_noo_pushed_edp');
        DB::statement('DROP INDEX IF EXISTS idx_activity_logs_created_at');
    }
};
