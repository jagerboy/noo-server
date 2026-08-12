<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Perintah Artisan khusus untuk mengimpor berkas CSV ekspor NOO_MASTER / SPV_MASTER / COUNTER_SEQ
 * langsung ke tabel database PostgreSQL noo_v2_db.
 */
class ImportMasterCsvCommand extends Command
{
    protected $signature = 'noo:import-csv {file : Path absolute/relative file CSV} {type : Type data (branch|salesman|spv|outlet_type|sequence)}';
    protected $description = 'Mengimpor berkas CSV master ke PostgreSQL noo_v2_db & mereset PostgreSQL auto-increment sequence';

    public function handle(): int
    {
        $filePath = $this->argument('file');
        $type = strtolower((string) $this->argument('type'));

        if (!file_exists($filePath)) {
            $this->error("Berkas CSV tidak ditemukan: {$filePath}");
            return Command::FAILURE;
        }

        $handle = fopen($filePath, 'r');
        if (!$handle) {
            $this->error("Gagal membuka berkas CSV.");
            return Command::FAILURE;
        }

        $header = fgetcsv($handle, 4096, ',');
        if (!$header) {
            $this->error("Header CSV kosong.");
            fclose($handle);
            return Command::FAILURE;
        }

        // Clean BOM / Whitespace pada nama kolom header
        $cleanHeaders = array_map(fn($h) => strtolower(trim(preg_replace('/[\x00-\x1F\x7F-\xFF]/', '', $h))), $header);

        $count = 0;
        DB::beginTransaction();

        try {
            while (($row = fgetcsv($handle, 4096, ',')) !== false) {
                if (empty(array_filter($row))) continue;

                $data = [];
                foreach ($cleanHeaders as $idx => $headerName) {
                    $data[$headerName] = trim($row[$idx] ?? '');
                }

                switch ($type) {
                    case 'branch':
                        $this->importBranch($data);
                        break;
                    case 'salesman':
                        $this->importSalesman($data);
                        break;
                    case 'spv':
                        $this->importSpv($data);
                        break;
                    case 'outlet_type':
                        $this->importOutletType($data);
                        break;
                    case 'sequence':
                        $this->importCounterSequence($data);
                        break;
                    default:
                        throw new \Exception("Tipe impor tidak dikenal: {$type}");
                }

                $count++;
            }

            DB::commit();
            fclose($handle);

            // Sync PostgreSQL Auto-Increment Sequences
            $this->syncPostgresSequences();

            $this->info("SUCCESS: Berhasil mengimpor {$count} baris data ke PostgreSQL & mereset primary key sequences!");
            return Command::SUCCESS;
        } catch (Throwable $e) {
            DB::rollBack();
            fclose($handle);
            $this->error("ERROR: Gagal impor CSV: {$e->getMessage()}");
            return Command::FAILURE;
        }
    }

    protected function importBranch(array $d): void
    {
        $branchId = $d['branch_id'] ?? $d['kode_distributor'] ?? '';
        if (empty($branchId)) return;

        $isActive = true;
        if (isset($d['aktif'])) {
            $isActive = strtoupper($d['aktif']) === 'Y';
        } elseif (isset($d['active'])) {
            $isActive = filter_var($d['active'], FILTER_VALIDATE_BOOLEAN);
        }

        DB::table('master_branches')->updateOrInsert(
            ['branch_id' => $branchId],
            [
                'region_code' => $d['region_code'] ?? 'ASWSUM1',
                'region_name' => $d['region_name'] ?? 'SUMATERA 1',
                'principal_code' => $d['principal_code'] ?? 'A',
                'principal_name' => $d['principal_name'] ?? 'ASWFOODS',
                'entity_code_principal' => $d['entity_code_principal'] ?? 'ASW',
                'entity_name_principal' => $d['entity_name_principal'] ?? 'ASWFOODS',
                'area_code' => $d['area_code'] ?? 'SUM1',
                'branch_name' => $d['branch_name'] ?? $d['nama_distributor'] ?? $branchId,
                'pin_branch' => $d['pin_branch'] ?? $d['pin'] ?? '123456',
                'is_active' => $isActive,
                'updated_at' => now(),
            ]
        );
    }

    protected function importSalesman(array $d): void
    {
        $salesmanCode = $d['salesman_code'] ?? $d['kode_salesman'] ?? '';
        $branchId = $d['branch_id'] ?? '';
        if (empty($salesmanCode)) return;

        if (!empty($branchId)) {
            $exists = DB::table('master_branches')->where('branch_id', $branchId)->exists();
            if (!$exists) {
                DB::table('master_branches')->insert([
                    'branch_id' => $branchId,
                    'region_code' => $d['region_code'] ?? 'ASWSUM1',
                    'principal_code' => 'A',
                    'principal_name' => 'ASWFOODS',
                    'entity_code_principal' => $d['entity_code_principal'] ?? 'ASW',
                    'area_code' => 'SUM1',
                    'branch_name' => "DISTRIBUTOR {$branchId}",
                    'pin_branch' => '123456',
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        $isActive = true;
        if (isset($d['aktif'])) {
            $isActive = strtoupper($d['aktif']) === 'Y';
        } elseif (isset($d['active'])) {
            $isActive = filter_var($d['active'], FILTER_VALIDATE_BOOLEAN);
        }

        $regionCode = $d['region_code'] ?? $d['region_id'] ?? null;
        if (empty($regionCode) && !empty($branchId)) {
            $b = DB::table('master_branches')->where('branch_id', $branchId)->first();
            if ($b && !empty($b->region_code)) {
                $regionCode = $b->region_code;
            }
        }
        if (empty($regionCode)) {
            $regionCode = 'ASWSUM1';
        }

        DB::table('master_salesmen')->updateOrInsert(
            ['salesman_code' => $salesmanCode],
            [
                'branch_id' => $branchId,
                'salesman_name' => $d['salesman_name'] ?? $d['nama_salesman'] ?? $salesmanCode,
                'region_code' => strtoupper($regionCode),
                'entity_code_principal' => $d['entity_code_principal'] ?? $d['entity_id'] ?? 'ASW',
                'is_active' => $isActive,
                'updated_at' => now(),
            ]
        );
    }

    protected function importSpv(array $d): void
    {
        // SPV_MASTER.csv mempunyai header: username,password,nama,area,branch_id,distributor,aktif
        $salescode = $d['salescode'] ?? $d['username'] ?? $d['kode_spv'] ?? '';
        $branchId = $d['branch_id'] ?? '';
        if (empty($salescode)) return;

        // Pastikan branch_id terdaftar jika diisi
        if (!empty($branchId)) {
            $exists = DB::table('master_branches')->where('branch_id', $branchId)->exists();
            if (!$exists) {
                DB::table('master_branches')->insert([
                    'branch_id' => $branchId,
                    'region_code' => 'ASWSUM1',
                    'principal_code' => 'A',
                    'principal_name' => 'ASWFOODS',
                    'entity_code_principal' => 'ASW',
                    'area_code' => $d['area'] ?? 'SUM1',
                    'branch_name' => $d['distributor'] ?? "DISTRIBUTOR {$branchId}",
                    'pin_branch' => '123456',
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        $isActive = true;
        if (isset($d['aktif'])) {
            $isActive = strtoupper($d['aktif']) === 'Y';
        } elseif (isset($d['active'])) {
            $isActive = filter_var($d['active'], FILTER_VALIDATE_BOOLEAN);
        }

        DB::table('master_spvs')->updateOrInsert(
            ['salescode' => $salescode, 'branch_id' => $branchId],
            [
                'password' => $d['password'] ?? '123456',
                'nama' => $d['nama'] ?? $salescode,
                'area' => $d['area'] ?? 'SUM1',
                'branch_id' => $branchId,
                'distributor_name' => $d['distributor'] ?? '',
                'is_active' => $isActive,
                'updated_at' => now(),
            ]
        );
    }

    protected function importOutletType(array $d): void
    {
        $code = $d['code'] ?? $d['type_code'] ?? '';
        if (empty($code)) return;

        DB::table('master_outlet_types')->updateOrInsert(
            ['code' => $code],
            [
                'description' => $d['description'] ?? $d['type_desc'] ?? $code,
                'is_active' => true,
                'updated_at' => now(),
            ]
        );
    }

    protected function importCounterSequence(array $d): void
    {
        // COUNTER_SEQ.csv header: entity_code,area_code,branch_code,principal_prefix,last_seq,updated_at,updated_by
        $branchId = $d['branch_id'] ?? $d['branch_code'] ?? '';
        $pCode = $d['principal_code'] ?? $d['entity_code'] ?? 'A';
        $prefix = $d['prefix'] ?? $d['principal_prefix'] ?? '';
        if (empty($branchId)) return;

        // Pastikan branch_id terdaftar di master_branches
        $exists = DB::table('master_branches')->where('branch_id', $branchId)->exists();
        if (!$exists) {
            DB::table('master_branches')->insert([
                'branch_id' => $branchId,
                'region_code' => 'ASWSUM1',
                'principal_code' => $pCode,
                'principal_name' => 'ASWFOODS',
                'entity_code_principal' => 'ASW',
                'area_code' => $d['area_code'] ?? 'SUM1',
                'branch_name' => "DISTRIBUTOR {$branchId}",
                'pin_branch' => '123456',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('counter_sequences')->updateOrInsert(
            ['principal_code' => $pCode, 'branch_id' => $branchId],
            [
                'area_code' => $d['area_code'] ?? 'SUM1',
                'prefix' => $prefix,
                'last_seq' => (int) ($d['last_seq'] ?? 0),
                'last_updated_at' => now(),
                'updated_at' => now(),
            ]
        );
    }

    protected function syncPostgresSequences(): void
    {
        $tables = [
            'users',
            'master_spvs',
            'master_branches',
            'master_salesmen',
            'master_outlet_types',
            'counter_sequences',
            'activity_logs',
            'noo_submissions',
        ];

        foreach ($tables as $table) {
            try {
                DB::statement("SELECT setval('{$table}_id_seq', COALESCE((SELECT MAX(id) FROM {$table}), 1))");
            } catch (Throwable $e) {
                // Ignore if sequence doesn't exist
            }
        }
    }
}
