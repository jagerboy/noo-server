<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration skema database terpusat PostgreSQL untuk ekosistem NOO+ v2.0.
 * Menampung master cabang, salesman, SPV, tipe outlet, counter sequence,
 * transaksi pendaftaran NOO+, dan audit log.
 */
return new class extends Migration
{
    /**
     * Jalankan proses pembuatan tabel-tabel utama database NOO+ v2.0.
     */
    public function up(): void
    {
        // 1. Modifikasi tabel users bawaan untuk menampung role & scope wilayah
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 30)->default('ADMIN_DISTRIBUTOR')->after('email');
            $table->string('region_code', 50)->nullable()->after('role');
            $table->string('entity_code_principal', 50)->nullable()->after('region_code');
            $table->string('branch_id', 50)->nullable()->after('entity_code_principal');
            $table->string('salesman_code', 50)->nullable()->after('branch_id');
            $table->string('pin_branch', 50)->nullable()->after('salesman_code');
            $table->boolean('is_active')->default(true)->after('pin_branch');
        });

        // 2. Tabel Master Cabang / Distributor
        Schema::create('master_branches', function (Blueprint $table) {
            $table->id();
            $table->string('region_code', 50);
            $table->string('region_name', 100)->nullable();
            $table->string('principal_code', 20)->default('A');
            $table->string('principal_name', 100)->default('ASWFOODS');
            $table->string('entity_code_principal', 50);
            $table->string('entity_name_principal', 150)->nullable();
            $table->string('area_code', 50)->nullable();
            $table->string('branch_id', 50)->unique();
            $table->string('branch_name', 150);
            $table->string('pin_branch', 50);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 3. Tabel Master Salesman
        Schema::create('master_salesmen', function (Blueprint $table) {
            $table->id();
            $table->string('branch_id', 50);
            $table->string('salesman_code', 50)->unique();
            $table->string('salesman_name', 150);
            $table->string('region_code', 50)->nullable();
            $table->string('entity_code_principal', 50)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('branch_id')->references('branch_id')->on('master_branches')->onDelete('cascade');
        });

        // 4. Tabel Master SPV Area
        Schema::create('master_spvs', function (Blueprint $table) {
            $table->id();
            $table->string('salescode', 50)->unique();
            $table->string('password', 100);
            $table->string('nama', 150);
            $table->string('area', 50)->nullable();
            $table->string('branch_id', 50);
            $table->string('distributor_name', 150)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('branch_id')->references('branch_id')->on('master_branches')->onDelete('cascade');
        });

        // 5. Tabel Master Tipe Outlet
        Schema::create('master_outlet_types', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('description', 150);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 6. Tabel Counter Sequence Kode NOO Principal
        Schema::create('counter_sequences', function (Blueprint $table) {
            $table->id();
            $table->string('principal_code', 20);
            $table->string('area_code', 50)->nullable();
            $table->string('branch_id', 50);
            $table->string('prefix', 30);
            $table->unsignedBigInteger('last_seq')->default(0);
            $table->timestamp('last_updated_at')->nullable();
            $table->timestamps();

            $table->unique(['principal_code', 'branch_id']);
            $table->foreign('branch_id')->references('branch_id')->on('master_branches')->onDelete('cascade');
        });

        // 7. Tabel Utama Submisi NOO (Single Source of Truth Workflow NOO)
        Schema::create('noo_submissions', function (Blueprint $table) {
            $table->id();
            $table->uuid('request_id')->unique();
            $table->timestamp('submitted_at');

            // Data Hirarki Principal & Cabang
            $table->string('principal', 50)->default('ASWFOODS');
            $table->string('principal_code', 20)->default('A');
            $table->string('region_code', 50);
            $table->string('branch_id', 50);
            $table->string('branch_name', 150)->nullable();
            $table->string('area_code', 50)->nullable();

            // Data Salesman
            $table->string('salesman_code', 50);
            $table->string('salesman_name', 150)->nullable();

            // Data Outlet Utama
            $table->string('code_noo_principal', 50)->nullable()->index();
            $table->string('nama_noo', 200);
            $table->text('alamat_noo');
            $table->string('kel_noo', 100)->nullable();
            $table->string('kec_noo', 100)->nullable();
            $table->string('kab_kota_noo', 100)->nullable();
            $table->string('provinsi_noo', 100)->nullable();

            // Tipe Outlet & Lokasi GPS
            $table->string('type_outlet_code', 30);
            $table->string('type_outlet_desc', 150)->nullable();
            $table->double('la');
            $table->double('lg');
            $table->decimal('accuracy_m', 8, 2)->nullable();
            $table->integer('samples_count')->default(10);
            $table->integer('sampling_interval_sec')->default(1);
            $table->integer('geo_duration_sec')->default(30);

            // Foto Paths (Storage lokal server pengganti Drive)
            $table->string('photo_depan_path', 255)->nullable();
            $table->string('photo_dalam_path', 255)->nullable();
            $table->string('photo_ktp_path', 255)->nullable();
            $table->string('photo_status', 50)->default('PROGRESS');

            // Data Admin Distributor
            $table->string('custcode_distributor', 50)->nullable();
            $table->text('admin_notes')->nullable();
            $table->timestamp('pushed_to_spv_at')->nullable();

            // Data SPV Area (Rute H1-H7, M1-M4)
            $table->string('norute', 20)->nullable();
            $table->string('h1', 10)->nullable();
            $table->string('h2', 10)->nullable();
            $table->string('h3', 10)->nullable();
            $table->string('h4', 10)->nullable();
            $table->string('h5', 10)->nullable();
            $table->string('h6', 10)->nullable();
            $table->string('h7', 10)->nullable();
            $table->string('m1', 10)->nullable();
            $table->string('m2', 10)->nullable();
            $table->string('m3', 10)->nullable();
            $table->string('m4', 10)->nullable();
            $table->text('spv_notes')->nullable();
            $table->timestamp('spv_submit_at')->nullable();
            $table->string('approval_spv_area', 10)->nullable();
            $table->string('approved_by_spv', 50)->nullable();
            $table->timestamp('pushed_to_edp_at')->nullable();

            // Data Review EDP Principal
            $table->string('edp_decision', 30)->nullable();
            $table->text('edp_notes')->nullable();
            $table->timestamp('edp_reviewed_at')->nullable();
            $table->string('inject_status', 30)->nullable();
            $table->timestamp('injected_at')->nullable();
            $table->string('injected_by', 50)->nullable();

            // Meta Status Workflow Global
            $table->string('flags', 255)->nullable();
            $table->string('status', 50)->default('SE_SUBMITTED')->index();
            $table->timestamps();

            $table->foreign('branch_id')->references('branch_id')->on('master_branches')->onDelete('cascade');
        });

        // 8. Tabel Audit Log Aktivitas Perubahan Data
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->timestamp('timestamp');
            $table->string('username', 100);
            $table->string('role', 50);
            $table->string('action', 50);
            $table->string('table_name', 100);
            $table->string('row_key', 100);
            $table->string('field_name', 100)->nullable();
            $table->text('old_value')->nullable();
            $table->text('new_value')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Batalkan pembuatan tabel database.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('noo_submissions');
        Schema::dropIfExists('counter_sequences');
        Schema::dropIfExists('master_outlet_types');
        Schema::dropIfExists('master_spvs');
        Schema::dropIfExists('master_salesmen');
        Schema::dropIfExists('master_branches');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'role',
                'region_code',
                'entity_code_principal',
                'branch_id',
                'salesman_code',
                'pin_branch',
                'is_active'
            ]);
        });
    }
};
