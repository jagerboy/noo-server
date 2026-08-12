<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration penambahan kolom metadata NOO+ v2.0.0 ke tabel noo_submissions.
 * Menampung Sub-Grup Daerah (ASWFOODS/INAFOODS - Region), kontak pemilik,
 * koordinat locked/submit centroid, dan verifikasi EXIF geotagging foto toko.
 */
return new class extends Migration
{
    /**
     * Jalankan proses penambahan kolom-kolom baru.
     */
    public function up(): void
    {
        Schema::table('noo_submissions', function (Blueprint $table) {
            // 1. Sub-Grup Daerah (6 pilihan: ASWFOODS/INAFOODS - SUMATERA/JAWA/PULAU)
            if (!Schema::hasColumn('noo_submissions', 'sub_group_region')) {
                $table->string('sub_group_region', 100)->nullable()->after('principal');
            }

            // 2. Data Pemilik & Kontak
            if (!Schema::hasColumn('noo_submissions', 'nama_pemilik_outlet')) {
                $table->string('nama_pemilik_outlet', 150)->nullable()->after('nama_noo');
            }
            if (!Schema::hasColumn('noo_submissions', 'no_hp_noo')) {
                $table->string('no_hp_noo', 50)->nullable()->after('nama_pemilik_outlet');
            }
            if (!Schema::hasColumn('noo_submissions', 'no_hp')) {
                $table->string('no_hp', 50)->nullable()->after('no_hp_noo');
            }

            // 3. Metadata Koordinat Centroid (Locked) saat Sampling
            if (!Schema::hasColumn('noo_submissions', 'locked_la')) {
                $table->double('locked_la')->nullable()->after('geo_duration_sec');
            }
            if (!Schema::hasColumn('noo_submissions', 'locked_lg')) {
                $table->double('locked_lg')->nullable()->after('locked_la');
            }
            if (!Schema::hasColumn('noo_submissions', 'locked_accuracy_m')) {
                $table->decimal('locked_accuracy_m', 8, 2)->nullable()->after('locked_lg');
            }
            if (!Schema::hasColumn('noo_submissions', 'mock_flag_locked')) {
                $table->string('mock_flag_locked', 20)->nullable()->after('locked_accuracy_m');
            }

            // 4. Metadata Koordinat Instant saat Tombol Submit Ditekan
            if (!Schema::hasColumn('noo_submissions', 'submit_la')) {
                $table->double('submit_la')->nullable()->after('mock_flag_locked');
            }
            if (!Schema::hasColumn('noo_submissions', 'submit_lg')) {
                $table->double('submit_lg')->nullable()->after('submit_la');
            }
            if (!Schema::hasColumn('noo_submissions', 'submit_accuracy_m')) {
                $table->decimal('submit_accuracy_m', 8, 2)->nullable()->after('submit_lg');
            }
            if (!Schema::hasColumn('noo_submissions', 'mock_flag_submit')) {
                $table->string('mock_flag_submit', 20)->nullable()->after('submit_accuracy_m');
            }
            if (!Schema::hasColumn('noo_submissions', 'submit_distance_m')) {
                $table->decimal('submit_distance_m', 8, 2)->nullable()->after('mock_flag_submit');
            }
            if (!Schema::hasColumn('noo_submissions', 'submit_radius_m')) {
                $table->decimal('submit_radius_m', 8, 2)->nullable()->after('submit_distance_m');
            }

            // 5. Verifikasi Metadata EXIF Geotagging Foto Depan & Dalam (<15m)
            if (!Schema::hasColumn('noo_submissions', 'exif_depan_la')) {
                $table->double('exif_depan_la')->nullable()->after('photo_ktp_path');
            }
            if (!Schema::hasColumn('noo_submissions', 'exif_depan_lg')) {
                $table->double('exif_depan_lg')->nullable()->after('exif_depan_la');
            }
            if (!Schema::hasColumn('noo_submissions', 'exif_depan_distance_m')) {
                $table->decimal('exif_depan_distance_m', 8, 2)->nullable()->after('exif_depan_lg');
            }
            if (!Schema::hasColumn('noo_submissions', 'exif_dalam_la')) {
                $table->double('exif_dalam_la')->nullable()->after('exif_depan_distance_m');
            }
            if (!Schema::hasColumn('noo_submissions', 'exif_dalam_lg')) {
                $table->double('exif_dalam_lg')->nullable()->after('exif_dalam_la');
            }
            if (!Schema::hasColumn('noo_submissions', 'exif_dalam_distance_m')) {
                $table->decimal('exif_dalam_distance_m', 8, 2)->nullable()->after('exif_dalam_lg');
            }
            if (!Schema::hasColumn('noo_submissions', 'is_exif_valid')) {
                $table->boolean('is_exif_valid')->default(true)->after('exif_dalam_distance_m');
            }
        });
    }

    /**
     * Batalkan pembuatan kolom baru.
     */
    public function down(): void
    {
        Schema::table('noo_submissions', function (Blueprint $table) {
            $table->dropColumn([
                'sub_group_region',
                'nama_pemilik_outlet',
                'no_hp_noo',
                'no_hp',
                'locked_la',
                'locked_lg',
                'locked_accuracy_m',
                'mock_flag_locked',
                'submit_la',
                'submit_lg',
                'submit_accuracy_m',
                'mock_flag_submit',
                'submit_distance_m',
                'submit_radius_m',
                'exif_depan_la',
                'exif_depan_lg',
                'exif_depan_distance_m',
                'exif_dalam_la',
                'exif_dalam_lg',
                'exif_dalam_distance_m',
                'is_exif_valid',
            ]);
        });
    }
};
