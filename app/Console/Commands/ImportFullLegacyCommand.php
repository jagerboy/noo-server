<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\NooStatusEnum;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use PhpOffice\PhpSpreadsheet\IOFactory;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Throwable;

/**
 * Perintah Artisan untuk melakukan migrasi TOTAL data NOO+ dari sistem lama (Google Apps Script / Excel)
 * mencakup:
 * 1. Sheet Admin Distributor (02_ADMIN_SHEET - seluruh folder distributor)
 * 2. Sheet SPV Area (03_SPV_SHEET/NOO_SPV_JKS.xlsx)
 * 3. Sheet EDP Principal (04_EDP_SHEET/NOO_EDP_REVIEW.xlsx)
 *
 * Aturan Utama: Berdasarkan request_id (UUID). Jika request_id sudah ada di DB, JANGAN ditambahkan lagi.
 */
class ImportFullLegacyCommand extends Command
{
    protected $signature = 'noo:import-full-legacy
                            {path=D:\\AndroidStudioProjects\\NOO_SYSTEM-20260910T085622Z-1-001\\NOO_SYSTEM : Path direktori utama NOO_SYSTEM}
                            {--dry-run : Menjalankan simulasi pemindaian & validasi tanpa menyimpan ke database}';

    protected $description = 'Mengimpor seluruh data registrasi NOO+ dari sheet Admin, SPV, dan EDP berdasarkan request_id (skip yang sudah ada)';

    public function handle(): int
    {
        @ini_set('memory_limit', '2048M');
        @set_time_limit(0);

        $basePath = $this->argument('path');
        $isDryRun = (bool) $this->option('dry-run');

        $realBasePath = realpath($basePath);
        if (!$realBasePath || !is_dir($realBasePath)) {
            $this->error("❌ Direktori utama NOO_SYSTEM tidak ditemukan: {$basePath}");
            return Command::FAILURE;
        }

        $this->info("🚀 Memulai Impor Migrasi Total NOO+ dari: <comment>{$realBasePath}</comment>" . ($isDryRun ? " (SIMULASI / DRY-RUN)" : ""));

        // 1. Ambil daftar request_id yang sudah ada di database noo_submissions
        $existingRequestIds = [];
        try {
            $existingRequestIds = DB::table('noo_submissions')
                ->pluck('request_id')
                ->filter()
                ->map(fn($id) => strtolower(trim((string)$id)))
                ->flip()
                ->toArray();

            $this->info("🔍 Total data toko di database saat ini: <info>" . count($existingRequestIds) . "</info> records.");
        } catch (Throwable $e) {
            $this->error("❌ Gagal membaca database noo_submissions: {$e->getMessage()}");
            return Command::FAILURE;
        }

        $candidates = []; // Map request_id => data record
        $scannedAdminFiles = 0;
        $totalAdminRows = 0;

        // -------------------------------------------------------------------------
        // TAHAP 1: Pindai Seluruh File Excel Admin Distributor di 02_ADMIN_SHEET
        // -------------------------------------------------------------------------
        $adminDir = $realBasePath . DIRECTORY_SEPARATOR . '02_ADMIN_SHEET';
        if (is_dir($adminDir)) {
            $this->line("📂 Memindai sheet Admin Distributor di: <comment>{$adminDir}</comment>...");
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($adminDir));

            foreach ($iterator as $fileInfo) {
                if ($fileInfo->isDir()) continue;
                $ext = strtolower($fileInfo->getExtension());
                if (!in_array($ext, ['xlsx', 'xls'])) continue;

                $filePath = $fileInfo->getRealPath();
                $fileName = $fileInfo->getFilename();

                // Skip file template
                if (str_contains(strtolower($fileName), 'template')) continue;

                $scannedAdminFiles++;
                $this->line("  📄 Membaca file Admin ({$scannedAdminFiles}): <comment>{$fileName}</comment>");

                try {
                    $reader = IOFactory::createReaderForFile($filePath);
                    $reader->setReadDataOnly(true);
                    $spreadsheet = $reader->load($filePath);
                    $sheet = $spreadsheet->getSheetByName('NOO_INBOX') ?: $spreadsheet->getActiveSheet();
                    $rows = $sheet->toArray(null, false, false, true);

                    if (count($rows) < 2) continue;

                    // Cari header row (baris 1 atau baris 2 yang ada 'request_id')
                    $headerRowIndex = null;
                    $headerMap = [];

                    foreach ($rows as $rIdx => $rowValues) {
                        $cleanedValues = array_map(fn($v) => strtolower(trim((string)$v)), $rowValues);
                        if (in_array('request_id', $cleanedValues)) {
                            $headerRowIndex = $rIdx;
                            foreach ($cleanedValues as $colLetter => $colName) {
                                if ($colName !== '') {
                                    $headerMap[$colName] = $colLetter;
                                }
                            }
                            break;
                        }
                    }

                    if (!$headerRowIndex || !isset($headerMap['request_id'])) {
                        continue;
                    }

                    // Iterasi baris data setelah header
                    foreach ($rows as $rIdx => $rowValues) {
                        if ($rIdx <= $headerRowIndex) continue;

                        $reqId = strtolower(trim((string)($rowValues[$headerMap['request_id']] ?? '')));
                        if (empty($reqId) || strlen($reqId) < 10) continue;

                        $totalAdminRows++;

                        // Parse fields dari Admin Sheet
                        $rec = $this->parseAdminRow($rowValues, $headerMap);

                        // RULE KUNCI: Jika sudah ada di DB, perbarui submitted_at jika nilainya 1970-01-01
                        if (isset($existingRequestIds[$reqId])) {
                            if (!empty($rec['submitted_at']) && !str_starts_with($rec['submitted_at'], '1970')) {
                                DB::table('noo_submissions')
                                    ->where('request_id', $reqId)
                                    ->where('submitted_at', 'like', '1970%')
                                    ->update([
                                        'submitted_at' => $rec['submitted_at'],
                                        'updated_at' => now()->toDateTimeString(),
                                    ]);
                            }
                            continue;
                        }

                        // Jika sudah masuk kandidat dari file admin lain, skip
                        if (isset($candidates[$reqId])) {
                            continue;
                        }

                        if (!empty($rec['request_id'])) {
                            $candidates[$reqId] = $rec;
                        }
                    }

                    $spreadsheet->disconnectWorksheets();
                    unset($spreadsheet);
                } catch (Throwable $e) {
                    $this->warn("   ⚠️ Gagal membaca file {$fileName}: {$e->getMessage()}");
                }
            }
        }

        $this->info("✅ Tahap 1 Selesai: Menemukan <info>" . count($candidates) . "</info> pengajuan baru dari Admin Distributor.");

        // -------------------------------------------------------------------------
        // TAHAP 2: Pembacaan & Penggabungan Sheet SPV (03_SPV_SHEET/NOO_SPV_JKS.xlsx)
        // -------------------------------------------------------------------------
        $spvFile = $realBasePath . DIRECTORY_SEPARATOR . '03_SPV_SHEET' . DIRECTORY_SEPARATOR . 'NOO_SPV_JKS.xlsx';
        if (file_exists($spvFile)) {
            $this->line("📂 Membaca sheet SPV Area di: <comment>{$spvFile}</comment>...");
            try {
                $reader = IOFactory::createReaderForFile($spvFile);
                $reader->setReadDataOnly(true);
                $spreadsheet = $reader->load($spvFile);
                $sheet = $spreadsheet->getSheetByName('JKS_QUEUE') ?: $spreadsheet->getActiveSheet();
                $rows = $sheet->toArray(null, false, false, true);

                $headerRowIndex = null;
                $headerMap = [];

                foreach ($rows as $rIdx => $rowValues) {
                    $cleanedValues = array_map(fn($v) => strtolower(trim((string)$v)), $rowValues);
                    if (in_array('request_id', $cleanedValues)) {
                        $headerRowIndex = $rIdx;
                        foreach ($cleanedValues as $colLetter => $colName) {
                            if ($colName !== '') {
                                $headerMap[$colName] = $colLetter;
                            }
                        }
                        break;
                    }
                }

                if ($headerRowIndex && isset($headerMap['request_id'])) {
                    foreach ($rows as $rIdx => $rowValues) {
                        if ($rIdx <= $headerRowIndex) continue;

                        $reqId = strtolower(trim((string)($rowValues[$headerMap['request_id']] ?? '')));
                        if (empty($reqId) || strlen($reqId) < 10) continue;

                        // Jika sudah di DB, skip
                        if (isset($existingRequestIds[$reqId])) continue;

                        // Jika belum ada di kandidat (misal belum terdaftar di Admin sheet), buat record baru
                        if (!isset($candidates[$reqId])) {
                            $candidates[$reqId] = $this->parseSpvRow($rowValues, $headerMap);
                        } else {
                            // Merge SPV data ke kandidat yang ada
                            $this->mergeSpvData($candidates[$reqId], $rowValues, $headerMap);
                        }
                    }
                }

                $spreadsheet->disconnectWorksheets();
                unset($spreadsheet);
                $this->info("✅ Tahap 2 Selesai: Berhasil menggabungkan data rute SPV Area.");
            } catch (Throwable $e) {
                $this->warn("⚠️ Gagal membaca file SPV: {$e->getMessage()}");
            }
        }

        // -------------------------------------------------------------------------
        // TAHAP 3: Pembacaan & Penggabungan Sheet EDP (04_EDP_SHEET/NOO_EDP_REVIEW.xlsx)
        // -------------------------------------------------------------------------
        $edpFile = $realBasePath . DIRECTORY_SEPARATOR . '04_EDP_SHEET' . DIRECTORY_SEPARATOR . 'NOO_EDP_REVIEW.xlsx';
        if (file_exists($edpFile)) {
            $this->line("📂 Membaca sheet EDP Principal di: <comment>{$edpFile}</comment>...");
            try {
                $reader = IOFactory::createReaderForFile($edpFile);
                $reader->setReadDataOnly(true);
                $spreadsheet = $reader->load($edpFile);
                $sheet = $spreadsheet->getSheetByName('EDP_REVIEW_QUEUE') ?: $spreadsheet->getActiveSheet();
                $rows = $sheet->toArray(null, false, false, true);

                $headerRowIndex = null;
                $headerMap = [];

                foreach ($rows as $rIdx => $rowValues) {
                    $cleanedValues = array_map(fn($v) => strtolower(trim((string)$v)), $rowValues);
                    if (in_array('request_id', $cleanedValues)) {
                        $headerRowIndex = $rIdx;
                        foreach ($cleanedValues as $colLetter => $colName) {
                            if ($colName !== '') {
                                $headerMap[$colName] = $colLetter;
                            }
                        }
                        break;
                    }
                }

                if ($headerRowIndex && isset($headerMap['request_id'])) {
                    foreach ($rows as $rIdx => $rowValues) {
                        if ($rIdx <= $headerRowIndex) continue;

                        $reqId = strtolower(trim((string)($rowValues[$headerMap['request_id']] ?? '')));
                        if (empty($reqId) || strlen($reqId) < 10) continue;

                        // Jika sudah di DB, skip
                        if (isset($existingRequestIds[$reqId])) continue;

                        if (!isset($candidates[$reqId])) {
                            $candidates[$reqId] = $this->parseEdpRow($rowValues, $headerMap);
                        } else {
                            $this->mergeEdpData($candidates[$reqId], $rowValues, $headerMap);
                        }
                    }
                }

                $spreadsheet->disconnectWorksheets();
                unset($spreadsheet);
                $this->info("✅ Tahap 3 Selesai: Berhasil menggabungkan data keputusan EDP Principal.");
            } catch (Throwable $e) {
                $this->warn("⚠️ Gagal membaca file EDP: {$e->getMessage()}");
            }
        }

        $totalNewToImport = count($candidates);
        $this->info("\n📊 RINGKASAN PEMINDAIAN MIGRASI:");
        $this->table(
            ['Metrik Migrasi', 'Jumlah'],
            [
                ['Total File Admin Diperiksa', $scannedAdminFiles],
                ['Total Baris Submisi Diperiksa', $totalAdminRows],
                ['Data Sudah Ada di Database (Skipped)', count($existingRequestIds)],
                ['Data Toko Baru Siap Diimpor', $totalNewToImport],
            ]
        );

        if ($totalNewToImport === 0) {
            $this->info("✨ Semua data pengajuan dari sheet lama sudah ada di database. Tidak ada record baru yang ditambahkan.");
            return Command::SUCCESS;
        }

        if ($isDryRun) {
            $this->warn("⚠️ Mode Dry-Run aktif. Mengabaikan penyimpanan ke database PostgreSQL.");
            return Command::SUCCESS;
        }

        // -------------------------------------------------------------------------
        // TAHAP 4: Simpan Data Baru ke PostgreSQL noo_submissions
        // -------------------------------------------------------------------------
        $this->line("\n⏳ Menyimpan <comment>{$totalNewToImport}</comment> data toko baru ke database PostgreSQL...");
        $insertedCount = 0;
        $batch = [];

        DB::beginTransaction();
        try {
            $this->ensureBranchesExist($candidates);

            foreach ($candidates as $rec) {
                $this->sanitizeRecord($rec);
                ksort($rec);
                $batch[] = $rec;
                if (count($batch) >= 100) {
                    DB::table('noo_submissions')->insert($batch);
                    $insertedCount += count($batch);
                    $batch = [];
                }
            }

            if (!empty($batch)) {
                DB::table('noo_submissions')->insert($batch);
                $insertedCount += count($batch);
            }

            DB::commit();
            $this->info("🎉 MIGRASI TOTAL SUCCESSFUL! Berhasil mengimpor <info>{$insertedCount}</info> pengajuan toko baru ke PostgreSQL.");
        } catch (Throwable $e) {
            DB::rollBack();
            $this->error("❌ Gagal menyimpan data ke PostgreSQL: {$e->getMessage()}");
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    private function parseAdminRow(array $r, array $map): array
    {
        $reqId = strtolower(trim((string)($r[$map['request_id']] ?? '')));
        $submittedAtRaw = $this->val($r, $map, 'submitted_at');
        $submittedAt = $this->parseDate($submittedAtRaw) ?: now();

        $branchId = strtoupper($this->val($r, $map, 'branch_id'));
        $codeNoo = strtoupper($this->val($r, $map, 'code_noo_principal'));
        $custDist = $this->val($r, $map, 'custcode_distributor');

        $statusRaw = strtoupper($this->val($r, $map, 'status'));
        $status = NooStatusEnum::SE_SUBMITTED->value;
        if (in_array($statusRaw, ['PUSHED_TO_SPV', 'ADMIN_APPROVED'])) {
            $status = NooStatusEnum::PUSHED_TO_SPV->value;
        } elseif (in_array($statusRaw, ['ADMIN_REJECTED', 'REJECTED_ADMIN'])) {
            $status = NooStatusEnum::ADMIN_REJECTED->value;
        } elseif (in_array($statusRaw, ['APPROVED_SPV', 'APPROVED_BY_SPV'])) {
            $status = NooStatusEnum::APPROVED_SPV->value;
        } elseif (in_array($statusRaw, ['SPV_REJECTED', 'REJECTED_SPV'])) {
            $status = NooStatusEnum::REJECTED_SPV->value;
        } elseif (in_array($statusRaw, ['APPROVED_EDP', 'EDP_APPROVED'])) {
            $status = NooStatusEnum::APPROVED_EDP->value;
        } elseif (in_array($statusRaw, ['EDP_REJECTED', 'REJECTED_EDP'])) {
            $status = NooStatusEnum::REJECTED_EDP->value;
        }

        $now = now()->toDateTimeString();

        return [
            'request_id' => $reqId,
            'submitted_at' => $submittedAt->toDateTimeString(),
            'principal' => $this->val($r, $map, 'principal') ?: 'ASWFOODS',
            'principal_code' => strtoupper($this->val($r, $map, 'principal_code')) ?: 'A',
            'region_code' => strtoupper($this->val($r, $map, 'region_code')) ?: 'SUMATERA',
            'branch_id' => $branchId,
            'branch_name' => $this->val($r, $map, 'branch_name'),
            'area_code' => strtoupper($this->val($r, $map, 'area_code')),

            'salesman_code' => strtoupper($this->val($r, $map, 'salesman_code')),
            'salesman_name' => $this->val($r, $map, 'salesman_name'),

            'code_noo_principal' => $codeNoo ?: null,
            'nama_noo' => $this->val($r, $map, 'nama_noo') ?: 'Toko Legasi',
            'nama_pemilik_outlet' => $this->val($r, $map, 'nama_pemilik_outlet') ?: $this->val($r, $map, 'nama_pemilik'),
            'no_hp_noo' => $this->val($r, $map, 'no_hp_noo') ?: $this->val($r, $map, 'no_hp'),
            'no_hp' => $this->val($r, $map, 'no_hp'),

            'alamat_noo' => $this->val($r, $map, 'alamat_noo') ?: '-',
            'kel_noo' => $this->val($r, $map, 'kel_noo'),
            'kec_noo' => $this->val($r, $map, 'kec_noo'),
            'kab_kota_noo' => $this->val($r, $map, 'kab_kota_noo'),
            'provinsi_noo' => $this->val($r, $map, 'provinsi_noo'),

            'type_outlet_code' => strtoupper($this->val($r, $map, 'type_outlet_code')) ?: 'RKO',
            'type_outlet_desc' => $this->val($r, $map, 'type_outlet_desc'),

            'la' => (float)($this->val($r, $map, 'la') ?: 0),
            'lg' => (float)($this->val($r, $map, 'lg') ?: 0),
            'accuracy_m' => (float)($this->val($r, $map, 'accuracy_m') ?: 10),
            'samples_count' => (int)($this->val($r, $map, 'samples_count') ?: 10),
            'sampling_interval_sec' => (int)($this->val($r, $map, 'sampling_interval_sec') ?: 1),
            'geo_duration_sec' => (int)($this->val($r, $map, 'geo_duration_sec') ?: 30),

            'photo_depan_path' => null,
            'photo_dalam_path' => null,
            'photo_ktp_path' => null,
            'photo_status' => 'READY',

            'custcode_distributor' => $custDist ?: null,
            'admin_notes' => $this->val($r, $map, 'admin_notes'),
            'pushed_to_spv_at' => $this->parseDate($this->val($r, $map, 'pushed_to_spv_at'))?->toDateTimeString(),

            'norute' => $this->val($r, $map, 'norute'),
            'h1' => strtoupper($this->val($r, $map, 'h1')),
            'h2' => strtoupper($this->val($r, $map, 'h2')),
            'h3' => strtoupper($this->val($r, $map, 'h3')),
            'h4' => strtoupper($this->val($r, $map, 'h4')),
            'h5' => strtoupper($this->val($r, $map, 'h5')),
            'h6' => strtoupper($this->val($r, $map, 'h6')),
            'h7' => strtoupper($this->val($r, $map, 'h7')),
            'm1' => strtoupper($this->val($r, $map, 'm1')),
            'm2' => strtoupper($this->val($r, $map, 'm2')),
            'm3' => strtoupper($this->val($r, $map, 'm3')),
            'm4' => strtoupper($this->val($r, $map, 'm4')),
            'spv_notes' => $this->val($r, $map, 'spv_notes'),
            'spv_submit_at' => null,
            'approval_spv_area' => null,

            'edp_decision' => null,
            'edp_notes' => null,
            'edp_reviewed_at' => null,

            'status' => $status,
            'flags' => $this->val($r, $map, 'flags'),
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }

    private function parseSpvRow(array $r, array $map): array
    {
        $rec = $this->parseAdminRow($r, $map);
        $this->mergeSpvData($rec, $r, $map);
        return $rec;
    }

    private function mergeSpvData(array &$rec, array $r, array $map): void
    {
        $norute = $this->val($r, $map, 'norute');
        if ($norute) $rec['norute'] = $norute;

        foreach (['h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'h7', 'm1', 'm2', 'm3', 'm4'] as $k) {
            $val = strtoupper($this->val($r, $map, $k));
            if ($val) $rec[$k] = $val;
        }

        $spvNotes = $this->val($r, $map, 'spv_notes');
        if ($spvNotes) $rec['spv_notes'] = $spvNotes;

        $spvSubmitAt = $this->parseDate($this->val($r, $map, 'spv_submit_at'));
        if ($spvSubmitAt) $rec['spv_submit_at'] = $spvSubmitAt->toDateTimeString();

        $apprSpv = strtoupper($this->val($r, $map, 'approval_spv_area'));
        if (in_array($apprSpv, ['APPROVED_SPV', 'APPROVED', 'YES', 'Y'])) {
            $rec['approval_spv_area'] = 'APPROVED';
            if ($rec['status'] === NooStatusEnum::PUSHED_TO_SPV->value) {
                $rec['status'] = NooStatusEnum::APPROVED_SPV->value;
            }
        } elseif (in_array($apprSpv, ['REJECTED_SPV', 'REJECTED', 'NO', 'N'])) {
            $rec['approval_spv_area'] = 'REJECTED';
            $rec['status'] = NooStatusEnum::REJECTED_SPV->value;
        }
    }

    private function parseEdpRow(array $r, array $map): array
    {
        $rec = $this->parseAdminRow($r, $map);
        $this->mergeEdpData($rec, $r, $map);
        return $rec;
    }

    private function mergeEdpData(array &$rec, array $r, array $map): void
    {
        $this->mergeSpvData($rec, $r, $map);

        $codeNoo = strtoupper($this->val($r, $map, 'code_noo_principal'));
        if ($codeNoo) $rec['code_noo_principal'] = $codeNoo;

        $edpDecision = strtoupper($this->val($r, $map, 'edp_decision'));
        $edpNotes = $this->val($r, $map, 'edp_notes');
        $edpRevAt = $this->parseDate($this->val($r, $map, 'edp_reviewed_at'));

        if ($edpDecision) $rec['edp_decision'] = $edpDecision;
        if ($edpNotes) $rec['edp_notes'] = $edpNotes;
        if ($edpRevAt) $rec['edp_reviewed_at'] = $edpRevAt->toDateTimeString();

        if (in_array($edpDecision, ['APPROVED_EDP', 'APPROVED', 'YES'])) {
            $rec['status'] = NooStatusEnum::APPROVED_EDP->value;
        } elseif (in_array($edpDecision, ['REJECTED_EDP', 'REJECTED', 'NO'])) {
            $rec['status'] = NooStatusEnum::REJECTED_EDP->value;
        }
    }

    private function val(array $r, array $map, string $key): string
    {
        if (!isset($map[$key])) return '';
        $col = $map[$key];
        return trim((string)($r[$col] ?? ''));
    }

    private function parseDate($val): ?Carbon
    {
        if ($val === null || $val === '') return null;
        try {
            if (is_numeric($val)) {
                $num = (float)$val;
                if ($num > 30000 && $num < 70000) {
                    $dt = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($num);
                    return Carbon::instance($dt);
                }
            }
            return Carbon::parse((string)$val);
        } catch (Throwable) {
            return null;
        }
    }

    private function sanitizeRecord(array &$rec): void
    {
        $limits = [
            'principal' => 50,
            'principal_code' => 20,
            'region_code' => 50,
            'branch_id' => 50,
            'branch_name' => 150,
            'area_code' => 50,
            'salesman_code' => 50,
            'salesman_name' => 150,
            'code_noo_principal' => 50,
            'nama_noo' => 200,
            'nama_pemilik_outlet' => 150,
            'no_hp_noo' => 50,
            'no_hp' => 50,
            'kel_noo' => 100,
            'kec_noo' => 100,
            'kab_kota_noo' => 100,
            'provinsi_noo' => 100,
            'type_outlet_code' => 30,
            'type_outlet_desc' => 150,
            'photo_status' => 50,
            'custcode_distributor' => 50,
            'norute' => 20,
            'h1' => 10,
            'h2' => 10,
            'h3' => 10,
            'h4' => 10,
            'h5' => 10,
            'h6' => 10,
            'h7' => 10,
            'm1' => 10,
            'm2' => 10,
            'm3' => 10,
            'm4' => 10,
            'approval_spv_area' => 10,
            'edp_decision' => 30,
            'status' => 50,
        ];

        foreach ($limits as $field => $maxLen) {
            if (isset($rec[$field]) && is_string($rec[$field])) {
                $trimmed = trim($rec[$field]);
                $rec[$field] = $trimmed !== '' ? mb_substr($trimmed, 0, $maxLen) : null;
            }
        }
    }

    private function ensureBranchesExist(array $candidates): void
    {
        $existingBranches = DB::table('master_branches')
            ->pluck('branch_id')
            ->map(fn($id) => strtoupper(trim((string)$id)))
            ->flip()
            ->toArray();

        $newBranches = [];
        $now = now()->toDateTimeString();

        foreach ($candidates as $rec) {
            $bid = strtoupper(trim((string)($rec['branch_id'] ?? '')));
            if (empty($bid) || isset($existingBranches[$bid]) || isset($newBranches[$bid])) {
                continue;
            }

            $region = mb_substr(strtoupper($rec['region_code'] ?? 'SUMATERA'), 0, 50);
            $principalCode = mb_substr(strtoupper($rec['principal_code'] ?? 'A'), 0, 20);
            $principalName = mb_substr($rec['principal'] ?? 'ASWFOODS', 0, 100);
            $areaCode = mb_substr(strtoupper($rec['area_code'] ?? 'SUM1'), 0, 50);
            $branchName = mb_substr($rec['branch_name'] ?: "Cabang {$bid}", 0, 150);

            $newBranches[$bid] = [
                'region_code' => $region,
                'region_name' => $region,
                'principal_code' => $principalCode,
                'principal_name' => $principalName,
                'entity_code_principal' => 'ASWFOODS',
                'entity_name_principal' => 'ASWFOODS',
                'area_code' => $areaCode,
                'branch_id' => $bid,
                'branch_name' => $branchName,
                'pin_branch' => '123456',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if (!empty($newBranches)) {
            DB::table('master_branches')->insert(array_values($newBranches));
            $this->info("✨ Auto-create <info>" . count($newBranches) . "</info> cabang baru ke master_branches (" . implode(', ', array_slice(array_keys($newBranches), 0, 5)) . "...).");
        }
    }
}
