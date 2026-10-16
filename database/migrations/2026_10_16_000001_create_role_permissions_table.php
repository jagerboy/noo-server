<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('role_permissions', function (Blueprint $table) {
            $table->id();
            $table->string('role', 50); // SUPERADMIN, ADMIN_PRINCIPAL, EDP_REGION, ASM, RSM
            $table->string('permission_key', 100);
            $table->boolean('is_allowed')->default(false);
            $table->timestamps();

            $table->unique(['role', 'permission_key']);
        });

        // Seed default permission matrix
        $defaultMatrix = [
            'dashboard_view' => ['edp' => true, 'admin' => true, 'asm' => true, 'rsm' => true, 'super' => true],
            'inbox_view' => ['edp' => true, 'admin' => true, 'asm' => true, 'rsm' => true, 'super' => true],
            'inbox_approve' => ['edp' => true, 'admin' => true, 'asm' => false, 'rsm' => false, 'super' => true],
            'inbox_reject' => ['edp' => true, 'admin' => true, 'asm' => false, 'rsm' => false, 'super' => true],
            'inbox_edit_info' => ['edp' => true, 'admin' => true, 'asm' => false, 'rsm' => false, 'super' => true],
            'inbox_revise_ktp' => ['edp' => true, 'admin' => true, 'asm' => false, 'rsm' => false, 'super' => true],
            'inbox_unlock_ktp' => ['edp' => false, 'admin' => false, 'asm' => false, 'rsm' => false, 'super' => true],
            'inbox_reset' => ['edp' => false, 'admin' => true, 'asm' => false, 'rsm' => false, 'super' => true],
            'inbox_toggle_ro' => ['edp' => true, 'admin' => true, 'asm' => false, 'rsm' => false, 'super' => true],
            'inbox_export' => ['edp' => true, 'admin' => true, 'asm' => false, 'rsm' => false, 'super' => true],
            'monitoring_ro_view' => ['edp' => false, 'admin' => false, 'asm' => false, 'rsm' => false, 'super' => true],
            'monitoring_ro_upload' => ['edp' => false, 'admin' => false, 'asm' => false, 'rsm' => false, 'super' => true],
            'progress_view' => ['edp' => true, 'admin' => true, 'asm' => true, 'rsm' => true, 'super' => true],
            'progress_reset_admin' => ['edp' => false, 'admin' => true, 'asm' => false, 'rsm' => false, 'super' => true],
            'progress_reset_spv' => ['edp' => false, 'admin' => true, 'asm' => false, 'rsm' => false, 'super' => true],
            'master_region_view' => ['edp' => false, 'admin' => true, 'asm' => false, 'rsm' => false, 'super' => true],
            'master_region_edit' => ['edp' => false, 'admin' => false, 'asm' => false, 'rsm' => false, 'super' => true],
            'master_entity_view' => ['edp' => false, 'admin' => true, 'asm' => false, 'rsm' => false, 'super' => true],
            'master_entity_edit' => ['edp' => false, 'admin' => false, 'asm' => false, 'rsm' => false, 'super' => true],
            'master_branch_view' => ['edp' => true, 'admin' => true, 'asm' => false, 'rsm' => false, 'super' => true],
            'master_branch_edit' => ['edp' => false, 'admin' => true, 'asm' => false, 'rsm' => false, 'super' => true],
            'master_salesman_view' => ['edp' => true, 'admin' => true, 'asm' => false, 'rsm' => false, 'super' => true],
            'master_salesman_edit' => ['edp' => false, 'admin' => true, 'asm' => false, 'rsm' => false, 'super' => true],
            'master_spv_view' => ['edp' => true, 'admin' => true, 'asm' => false, 'rsm' => false, 'super' => true],
            'master_spv_edit' => ['edp' => false, 'admin' => true, 'asm' => false, 'rsm' => false, 'super' => true],
            'master_outlet_types_view' => ['edp' => true, 'admin' => true, 'asm' => false, 'rsm' => false, 'super' => true],
            'master_outlet_types_edit' => ['edp' => false, 'admin' => true, 'asm' => false, 'rsm' => false, 'super' => true],
            'master_counter_sequence_view' => ['edp' => false, 'admin' => true, 'asm' => false, 'rsm' => false, 'super' => true],
            'master_counter_sequence_edit' => ['edp' => false, 'admin' => true, 'asm' => false, 'rsm' => false, 'super' => true],
            'master_bulk_upload' => ['edp' => false, 'admin' => false, 'asm' => false, 'rsm' => false, 'super' => true],
            'sys_users' => ['edp' => false, 'admin' => false, 'asm' => false, 'rsm' => false, 'super' => true],
            'sys_role_matrix' => ['edp' => false, 'admin' => false, 'asm' => false, 'rsm' => false, 'super' => true],
            'sys_logs' => ['edp' => false, 'admin' => true, 'asm' => false, 'rsm' => false, 'super' => true],
        ];

        $roleMap = [
            'edp' => 'EDP_REGION',
            'admin' => 'ADMIN_PRINCIPAL',
            'asm' => 'ASM',
            'rsm' => 'RSM',
            'super' => 'SUPERADMIN',
        ];

        $rows = [];
        $now = now();
        foreach ($defaultMatrix as $permKey => $roles) {
            foreach ($roles as $rKey => $allowed) {
                $roleName = $roleMap[$rKey] ?? null;
                if ($roleName) {
                    $rows[] = [
                        'role' => $roleName,
                        'permission_key' => $permKey,
                        'is_allowed' => $allowed,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }
        }

        DB::table('role_permissions')->insert($rows);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('role_permissions');
    }
};
