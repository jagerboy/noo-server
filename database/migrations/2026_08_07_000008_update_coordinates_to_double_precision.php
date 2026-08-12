<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Jalankan migrasi untuk mengubah tipe data koordinat menjadi DOUBLE PRECISION.
     */
    public function up(): void
    {
        $columns = [
            'la',
            'lg',
            'locked_la',
            'locked_lg',
            'submit_la',
            'submit_lg',
            'exif_depan_la',
            'exif_depan_lg',
            'exif_dalam_la',
            'exif_dalam_lg',
        ];

        foreach ($columns as $column) {
            DB::statement("ALTER TABLE noo_submissions ALTER COLUMN {$column} TYPE DOUBLE PRECISION USING {$column}::double precision");
        }
    }

    /**
     * Kembalikan migrasi ke DECIMAL(10, 7).
     */
    public function down(): void
    {
        $columns = [
            'la',
            'lg',
            'locked_la',
            'locked_lg',
            'submit_la',
            'submit_lg',
            'exif_depan_la',
            'exif_depan_lg',
            'exif_dalam_la',
            'exif_dalam_lg',
        ];

        foreach ($columns as $column) {
            DB::statement("ALTER TABLE noo_submissions ALTER COLUMN {$column} TYPE DECIMAL(10, 7) USING {$column}::decimal(10, 7)");
        }
    }
};
