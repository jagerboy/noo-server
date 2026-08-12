<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CsvMasterDataSeeder extends Seeder
{
    public function run(): void
    {
        $this->importCounterSequences();
        $this->importOutletTypes();
    }

    private function importCounterSequences(): void
    {
        $filePath = base_path('COUNTER_SEQ.csv');
        if (!file_exists($filePath)) {
            $this->command->error("File COUNTER_SEQ.csv tidak ditemukan di " . $filePath);
            return;
        }

        $handle = fopen($filePath, 'r');
        if (!$handle) return;

        // Skip header
        $header = fgetcsv($handle);

        $count = 0;
        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) < 5) continue;

            $entityCode = trim($row[0]);
            $areaCode   = trim($row[1]);
            $branchCode = strtoupper(trim($row[2]));
            $prefix     = strtoupper(trim($row[3]));
            $lastSeq    = (int) trim($row[4]);
            $updatedAtStr = !empty($row[5]) ? trim($row[5]) : null;

            $updatedAt = now();
            if ($updatedAtStr) {
                $ts = strtotime($updatedAtStr);
                if ($ts !== false) {
                    $updatedAt = date('Y-m-d H:i:s', $ts);
                }
            }

            DB::table('counter_sequences')->updateOrInsert(
                [
                    'principal_code' => $entityCode ?: 'A',
                    'branch_id' => $branchCode,
                ],
                [
                    'area_code' => $areaCode,
                    'prefix' => $prefix,
                    'last_seq' => $lastSeq,
                    'last_updated_at' => $updatedAt,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
            $count++;
        }
        fclose($handle);
        $this->command->info("Berhasil mengimpor {$count} data counter_sequences dari COUNTER_SEQ.csv.");
    }

    private function importOutletTypes(): void
    {
        $filePath = base_path('MASTER_OUTLET_TYPE.csv');
        if (!file_exists($filePath)) {
            $this->command->error("File MASTER_OUTLET_TYPE.csv tidak ditemukan di " . $filePath);
            return;
        }

        $handle = fopen($filePath, 'r');
        if (!$handle) return;

        // Skip header
        $header = fgetcsv($handle);

        $count = 0;
        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) < 2) continue;

            $typeCode = strtoupper(trim($row[0]));
            $typeDesc = trim($row[1]);
            $activeStr = isset($row[2]) ? strtoupper(trim($row[2])) : 'Y';
            $isActive = ($activeStr === 'Y' || $activeStr === 'TRUE' || $activeStr === '1');

            DB::table('master_outlet_types')->updateOrInsert(
                ['code' => $typeCode],
                [
                    'description' => $typeDesc,
                    'is_active' => $isActive,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
            $count++;
        }
        fclose($handle);
        $this->command->info("Berhasil mengimpor {$count} data master_outlet_types dari MASTER_OUTLET_TYPE.csv.");
    }
}
