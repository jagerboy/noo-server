<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Seeder data dummy untuk pengujian end-to-end ekosistem NOO+ v2.0.
 * Menyiapkan user login, master cabang DAMDN003, salesman SEAMDN32,
 * SPV area, tipe outlet, dan counter sequence penomoran.
 */
class NooTestingSeeder extends Seeder
{
    /**
     * Jalankan seeder data pengujian.
     */
    public function run(): void
    {
        // 1. Data Master Cabang / Distributor (DAMDN003 - Medan)
        DB::table('master_branches')->updateOrInsert(
            ['branch_id' => 'DAMDN003'],
            [
                'region_code' => 'ASWSUM1',
                'region_name' => 'SUMATERA 1',
                'principal_code' => 'A',
                'principal_name' => 'ASWFOODS',
                'entity_code_principal' => 'ASW',
                'entity_name_principal' => 'ASWFOODS MEDAN',
                'area_code' => 'SUM1',
                'branch_name' => 'CV. DWI TUNGGAL SENTOSA',
                'pin_branch' => '123456',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        // 2. Data Master Salesman (SEAMDN32)
        DB::table('master_salesmen')->updateOrInsert(
            ['salesman_code' => 'SEAMDN32'],
            [
                'branch_id' => 'DAMDN003',
                'salesman_name' => 'KURNIA SE',
                'region_code' => 'ASWSUM1',
                'entity_code_principal' => 'ASW',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        // 3. Data Master SPV Area
        DB::table('master_spvs')->updateOrInsert(
            ['salescode' => 'SPVMEDAN01'],
            [
                'password' => '123456',
                'nama' => 'BUDI SPV MEDAN',
                'area' => 'SUM1',
                'branch_id' => 'DAMDN003',
                'distributor_name' => 'CV. DWI TUNGGAL SENTOSA',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        // 4. Data Master Tipe Outlet
        $types = [
            ['code' => 'GT01', 'description' => 'STAR OUTLET'],
            ['code' => 'GT02', 'description' => 'GROSIR'],
            ['code' => 'GT03', 'description' => 'SEMI GROSIR'],
            ['code' => 'GT04', 'description' => 'RETAIL'],
        ];

        foreach ($types as $t) {
            DB::table('master_outlet_types')->updateOrInsert(
                ['code' => $t['code']],
                [
                    'description' => $t['description'],
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }

        // 5. Data Counter Sequence Penomoran
        DB::table('counter_sequences')->updateOrInsert(
            ['principal_code' => 'A', 'branch_id' => 'DAMDN003'],
            [
                'area_code' => 'SUM1',
                'prefix' => 'MED',
                'last_seq' => 14,
                'last_updated_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        // 6. User Login Web Portals
        $users = [
            [
                'name' => 'Admin Distributor Medan',
                'email' => 'admin.medan@distributor.com',
                'password' => Hash::make('123456'),
                'role' => 'ADMIN_DISTRIBUTOR',
                'branch_id' => 'DAMDN003',
                'region_code' => 'ASWSUM1',
            ],
            [
                'name' => 'SPV Area Medan',
                'email' => 'spv.medan@aswfoods.com',
                'password' => Hash::make('123456'),
                'role' => 'SPV',
                'branch_id' => 'DAMDN003',
                'salesman_code' => 'SPVMEDAN01',
                'region_code' => 'ASWSUM1',
            ],
            [
                'name' => 'EDP Principal SUM1',
                'email' => 'edp.sum1@aswfoods.com',
                'password' => Hash::make('123456'),
                'role' => 'EDP',
                'region_code' => 'ASWSUM1',
            ],
            [
                'name' => 'Superadmin Master',
                'email' => 'superadmin@aswfoods.com',
                'password' => Hash::make('123456'),
                'role' => 'SUPER_ADMIN',
            ]
        ];

        foreach ($users as $u) {
            DB::table('users')->updateOrInsert(
                ['email' => $u['email']],
                array_merge($u, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );
        }
    }
}
