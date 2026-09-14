<?php

declare(strict_types=1);

namespace App\Console\Commands;

use DateTime;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Throwable;

class ImportCsvV2Command extends Command
{
    protected $signature = 'noo:import-csv-v2
                            {csv=noo_v2.csv : Path ke berkas CSV noo_v2}
                            {photos=05_PHOTOS : Path ke direktori 05_PHOTOS}
                            {--dry-run : Menjalankan simulasi tanpa menyimpan ke database atau menyalin foto}';

    protected $description = 'Migrasi data noo_v2.csv dan pemetaan foto dari direktori 05_PHOTOS ke noo_submissions';

    public function handle(): int
    {
        $startTime = microtime(true);
        $isDryRun = (bool) $this->option('dry-run');

        $csvArg = $this->argument('csv');
        $photosArg = $this->argument('photos');

        $csvPath = realpath($csvArg) ?: base_path($csvArg);
        $photosPath = realpath($photosArg) ?: base_path($photosArg);

        if (!file_exists($csvPath)) {
            $this->error("❌ Berkas CSV tidak ditemukan: {$csvArg}");
            return Command::FAILURE;
        }

        if (!is_dir($photosPath)) {
            $this->error("❌ Direktori Foto tidak ditemukan: {$photosArg}");
            return Command::FAILURE;
        }

        $this->info("🚀 Memulai Migrasi CSV V2 & Mapping Foto 05_PHOTOS" . ($isDryRun ? " (DRY RUN)" : ""));
        $this->line("   CSV: <comment>{$csvPath}</comment>");
        $this->line("   Photos: <comment>{$photosPath}</comment>");

        // Pastikan kolom-kolom baru tersedia
        if (!$isDryRun) {
            try {
                DB::statement("ALTER TABLE noo_submissions ADD COLUMN IF NOT EXISTS reject_reason text null");
                DB::statement("ALTER TABLE noo_submissions ADD COLUMN IF NOT EXISTS reset_reason text null");
                DB::statement("ALTER TABLE noo_submissions ADD COLUMN IF NOT EXISTS is_ro boolean default true");
                DB::statement("ALTER TABLE noo_submissions ADD COLUMN IF NOT EXISTS previous_code_noo_principal varchar(50) null");
            } catch (Throwable $e) {
                // Ignore
            }
        }

        // Pindai Foto
        $this->line("📁 Memindai berkas foto di: {$photosPath}...");
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($photosPath, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        $photosByUuid = [];
        $photosByCode = [];
        $photosByBranchCode = [];
        $photosByBranchDate = [];
        $totalScannedFiles = 0;

        foreach ($iterator as $file) {
            if (!$file->isFile()) continue;
            $ext = strtolower($file->getExtension());
            if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) continue;

            $totalScannedFiles++;
            $fullPath = str_replace('\\', '/', $file->getRealPath());
            $filename = $file->getFilename();
            $dirName = basename(dirname($fullPath));

            $type = null;
            if (preg_match('/DEPAN/i', $filename)) {
                $type = 'DEPAN';
            } elseif (preg_match('/DALAM/i', $filename)) {
                $type = 'DALAM';
            } elseif (preg_match('/KTP/i', $filename)) {
                $type = 'KTP';
            } else {
                continue;
            }

            $branchId = null;
            if (preg_match('/BRANCH_([A-Z0-9]+)/i', $fullPath, $mb)) {
                $branchId = strtoupper($mb[1]);
            }

            $dateFolder = null;
            if (preg_match('/(\d{4}-\d{2}-\d{2})/', $fullPath, $md)) {
                $dateFolder = $md[1];
            }

            $uuid = null;
            if (preg_match('/([0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12})/i', $fullPath, $mu)) {
                $uuid = strtolower($mu[1]);
                $photosByUuid[$uuid][$type] = $fullPath;
            }

            $code = null;
            if (preg_match('/^([A-Z0-9\-]{3,20})$/i', $dirName) && !in_array(strtoupper($dirName), ['DEPAN','DALAM','KTP','PHOTOS','05_PHOTOS'])) {
                $code = strtoupper($dirName);
            } elseif (preg_match('/^([A-Z0-9\-]{3,20})_(DEPAN|DALAM|KTP)/i', $filename, $mc)) {
                $code = strtoupper($mc[1]);
            }

            if ($code && !$uuid) {
                $photosByCode[$code][$type] = $fullPath;
                if ($branchId) {
                    $photosByBranchCode[$branchId][$code][$type] = $fullPath;
                }
                if ($branchId && $dateFolder) {
                    $photosByBranchDate[$branchId][$dateFolder][$code][$type] = $fullPath;
                }
            }
        }

        $this->info("✅ Total berkas foto terindeks: {$totalScannedFiles} berkas.");

        // Baca CSV
        $fp = fopen($csvPath, 'r');
        $header = fgetcsv($fp);
        $validRows = [];

        while (($row = fgetcsv($fp)) !== false) {
            if (count($row) !== count($header)) continue;
            $rec = array_combine($header, $row);

            $reqId = strtolower(trim($rec['request_id'] ?? ''));
            $namaNoo = trim($rec['nama_noo'] ?? '');

            if (empty($reqId) && empty($namaNoo)) continue;

            if (isset($validRows[$reqId])) {
                $scoreOld = count(array_filter($validRows[$reqId]));
                $scoreNew = count(array_filter($rec));
                if ($scoreNew > $scoreOld) {
                    $validRows[$reqId] = $rec;
                }
            } else {
                $validRows[$reqId] = $rec;
            }
        }
        fclose($fp);

        $totalValid = count($validRows);
        $this->info("✅ Total toko unik dalam CSV: {$totalValid} toko.");

        $localStorageBase = storage_path('app/public');
        $photosCopiedCount = 0;
        $matchedPhotoOutlets = 0;
        $unmatchedPhotoOutlets = 0;

        // Auto branch registration
        if (!$isDryRun) {
            $existingBranches = DB::table('master_branches')->pluck('branch_id')->map(fn($b) => strtoupper($b))->flip()->toArray();
            foreach ($validRows as $r) {
                $bId = strtoupper(trim($r['branch_id'] ?? ''));
                if (!empty($bId) && !isset($existingBranches[$bId])) {
                    DB::table('master_branches')->insert([
                        'region_code'           => $this->parseStr($r['region_code'] ?? null) ?? 'UNKNOWN',
                        'region_name'           => $this->parseStr($r['region_code'] ?? null) ?? 'UNKNOWN',
                        'principal_code'        => $this->parseStr($r['principal_code'] ?? null) ?? 'A',
                        'principal_name'        => $this->parseStr($r['principal'] ?? null) ?? 'ASWFOODS',
                        'entity_code_principal' => $this->parseStr($r['region_code'] ?? null) ?? 'UNKNOWN',
                        'entity_name_principal' => $this->parseStr($r['region_code'] ?? null) ?? 'UNKNOWN',
                        'area_code'             => $this->parseStr($r['area_code'] ?? null),
                        'branch_id'             => $bId,
                        'branch_name'           => $this->parseStr($r['branch_name'] ?? null) ?? $bId,
                        'pin_branch'            => '123456',
                        'is_active'             => true,
                        'created_at'            => now(),
                        'updated_at'            => now(),
                    ]);
                    $existingBranches[$bId] = true;
                    $this->line("  🏢 Cabang baru didaftarkan: {$bId}");
                }
            }
        }

        $bar = $this->output->createProgressBar($totalValid);
        $bar->start();

        $usedBranchDateCodes = [];

        foreach ($validRows as $reqId => $r) {
            $branchId = $this->parseStr($r['branch_id'] ?? '') ?? 'UNKNOWN';
            $submittedAtRaw = $this->parseDateTime($r['submitted_at'] ?? '');
            $dateFolder = $submittedAtRaw ? date('Y-m-d', strtotime($submittedAtRaw)) : '2026-01-01';

            $code = strtoupper(trim($r['code_noo_principal'] ?? ''));
            $prevCode = strtoupper(trim($r['previous_code_noo_principal'] ?? ''));
            $custCode = strtoupper(trim($r['custcode_distributor'] ?? ''));

            $matchedPhotos = [];
            if ($reqId && isset($photosByUuid[$reqId])) {
                $matchedPhotos = $photosByUuid[$reqId];
            } elseif ($code && isset($photosByCode[$code])) {
                $matchedPhotos = $photosByCode[$code];
            } elseif ($prevCode && isset($photosByCode[$prevCode])) {
                $matchedPhotos = $photosByCode[$prevCode];
            } elseif ($custCode && isset($photosByCode[$custCode])) {
                $matchedPhotos = $photosByCode[$custCode];
            } elseif ($branchId && $code && isset($photosByBranchCode[$branchId][$code])) {
                $matchedPhotos = $photosByBranchCode[$branchId][$code];
            } elseif ($branchId && $dateFolder && isset($photosByBranchDate[$branchId][$dateFolder])) {
                foreach ($photosByBranchDate[$branchId][$dateFolder] as $candCode => $photoSet) {
                    if (!isset($usedBranchDateCodes[$candCode])) {
                        $usedBranchDateCodes[$candCode] = true;
                        $matchedPhotos = $photoSet;
                        break;
                    }
                }
            }

            $photoDepanPath = $this->parseStr($r['photo_depan_path'] ?? null);
            $photoDalamPath = $this->parseStr($r['photo_dalam_path'] ?? null);
            $photoKtpPath   = $this->parseStr($r['photo_ktp_path'] ?? null);
            $photoStatus    = $this->parseStr($r['photo_status'] ?? null) ?? 'PROGRESS';

            if (!empty($matchedPhotos)) {
                $matchedPhotoOutlets++;
                if (!$isDryRun) {
                    $targetDir = "{$localStorageBase}/noo_photos/{$branchId}/{$dateFolder}";
                    if (!is_dir($targetDir)) {
                        File::makeDirectory($targetDir, 0777, true, true);
                    }

                    foreach ($matchedPhotos as $type => $srcFile) {
                        $relPath = "noo_photos/{$branchId}/{$dateFolder}/{$reqId}_{$type}.jpg";
                        $destFile = "{$localStorageBase}/{$relPath}";
                        @copy($srcFile, $destFile);
                        $photosCopiedCount++;

                        if ($type === 'DEPAN') $photoDepanPath = $relPath;
                        if ($type === 'DALAM') $photoDalamPath = $relPath;
                        if ($type === 'KTP')   $photoKtpPath   = $relPath;
                    }
                }
                if (isset($matchedPhotos['DEPAN']) && isset($matchedPhotos['DALAM'])) {
                    $photoStatus = 'COMPLETED';
                }
            } else {
                $unmatchedPhotoOutlets++;
            }

            $recordData = [
                'request_id'             => $reqId,
                'submitted_at'           => $submittedAtRaw,
                'principal'              => $this->parseStr($r['principal'] ?? null) ?? 'ASWFOODS',
                'principal_code'         => $this->parseStr($r['principal_code'] ?? null) ?? 'A',
                'region_code'            => $this->parseStr($r['region_code'] ?? null) ?? 'UNKNOWN',
                'branch_id'              => $branchId,
                'branch_name'            => $this->parseStr($r['branch_name'] ?? null),
                'area_code'              => $this->parseStr($r['area_code'] ?? null),
                'salesman_code'          => $this->parseStr($r['salesman_code'] ?? null) ?? 'UNKNOWN',
                'salesman_name'          => $this->parseStr($r['salesman_name'] ?? null),
                'code_noo_principal'     => $this->parseStr($r['code_noo_principal'] ?? null),
                'nama_noo'               => $this->parseStr($r['nama_noo'] ?? null) ?? 'UNKNOWN',
                'alamat_noo'             => $this->parseStr($r['alamat_noo'] ?? null) ?? '-',
                'kel_noo'                => $this->parseStr($r['kel_noo'] ?? null),
                'kec_noo'                => $this->parseStr($r['kec_noo'] ?? null),
                'kab_kota_noo'           => $this->parseStr($r['kab_kota_noo'] ?? null),
                'provinsi_noo'           => $this->parseStr($r['provinsi_noo'] ?? null),
                'type_outlet_code'       => $this->parseStr($r['type_outlet_code'] ?? null) ?? 'GT04',
                'type_outlet_desc'       => $this->parseStr($r['type_outlet_desc'] ?? null) ?? 'RETAIL',
                'la'                     => $this->parseNumeric($r['la'] ?? null) ?? 0.0,
                'lg'                     => $this->parseNumeric($r['lg'] ?? null) ?? 0.0,
                'accuracy_m'             => $this->parseNumeric($r['accuracy_m'] ?? null),
                'samples_count'          => $this->parseInt($r['samples_count'] ?? null, 10),
                'sampling_interval_sec'  => $this->parseInt($r['sampling_interval_sec'] ?? null, 1),
                'geo_duration_sec'       => $this->parseInt($r['geo_duration_sec'] ?? null, 30),
                'photo_depan_path'       => $photoDepanPath,
                'photo_dalam_path'       => $photoDalamPath,
                'photo_ktp_path'         => $photoKtpPath,
                'photo_status'           => $photoStatus,
                'custcode_distributor'   => $this->parseStr($r['custcode_distributor'] ?? null),
                'admin_notes'            => $this->parseStr($r['admin_notes'] ?? null),
                'pushed_to_spv_at'       => $this->parseDateTime($r['pushed_to_spv_at'] ?? null),
                'norute'                 => $this->parseStr($r['norute'] ?? null),
                'h1'                     => $this->parseStr($r['h1'] ?? null),
                'h2'                     => $this->parseStr($r['h2'] ?? null),
                'h3'                     => $this->parseStr($r['h3'] ?? null),
                'h4'                     => $this->parseStr($r['h4'] ?? null),
                'h5'                     => $this->parseStr($r['h5'] ?? null),
                'h6'                     => $this->parseStr($r['h6'] ?? null),
                'h7'                     => $this->parseStr($r['h7'] ?? null),
                'm1'                     => $this->parseStr($r['m1'] ?? null),
                'm2'                     => $this->parseStr($r['m2'] ?? null),
                'm3'                     => $this->parseStr($r['m3'] ?? null),
                'm4'                     => $this->parseStr($r['m4'] ?? null),
                'spv_notes'              => $this->parseStr($r['spv_notes'] ?? null),
                'spv_submit_at'          => $this->parseDateTime($r['spv_submit_at'] ?? null),
                'approval_spv_area'      => $this->parseStr($r['approval_spv_area'] ?? null),
                'approved_by_spv'        => $this->parseStr($r['approved_by_spv'] ?? null),
                'pushed_to_edp_at'       => $this->parseDateTime($r['pushed_to_edp_at'] ?? null),
                'edp_decision'           => $this->parseStr($r['edp_decision'] ?? null),
                'edp_notes'              => $this->parseStr($r['edp_notes'] ?? null),
                'edp_reviewed_at'        => $this->parseDateTime($r['edp_reviewed_at'] ?? null),
                'inject_status'          => $this->parseStr($r['inject_status'] ?? null),
                'injected_at'            => $this->parseDateTime($r['injected_at'] ?? null),
                'injected_by'            => $this->parseStr($r['injected_by'] ?? null),
                'flags'                  => $this->parseStr($r['flags'] ?? null),
                'status'                 => $this->parseStr($r['status'] ?? null) ?? 'SE_SUBMITTED',
                'created_at'             => $this->parseDateTime($r['created_at'] ?? null) ?? now(),
                'updated_at'             => $this->parseDateTime($r['updated_at'] ?? null) ?? now(),
                'nama_pemilik_outlet'    => $this->parseStr($r['nama_pemilik_outlet'] ?? null),
                'no_hp'                  => $this->parseStr($r['no_hp'] ?? null),
                'no_hp_noo'              => $this->parseStr($r['no_hp_noo'] ?? null),
                'approved_by_admin'      => $this->parseStr($r['approved_by_admin'] ?? null),
                'approved_by_edp'        => $this->parseStr($r['approved_by_edp'] ?? null),
                'sub_group_region'       => $this->parseStr($r['sub_group_region'] ?? null),
                'locked_la'              => $this->parseNumeric($r['locked_la'] ?? null),
                'locked_lg'              => $this->parseNumeric($r['locked_lg'] ?? null),
                'locked_accuracy_m'      => $this->parseNumeric($r['locked_accuracy_m'] ?? null),
                'mock_flag_locked'       => $this->parseStr($r['mock_flag_locked'] ?? null),
                'submit_la'              => $this->parseNumeric($r['submit_la'] ?? null),
                'submit_lg'              => $this->parseNumeric($r['submit_lg'] ?? null),
                'submit_accuracy_m'      => $this->parseNumeric($r['submit_accuracy_m'] ?? null),
                'mock_flag_submit'       => $this->parseStr($r['mock_flag_submit'] ?? null),
                'submit_distance_m'      => $this->parseNumeric($r['submit_distance_m'] ?? null),
                'submit_radius_m'        => $this->parseNumeric($r['submit_radius_m'] ?? null),
                'exif_depan_la'          => $this->parseNumeric($r['exif_depan_la'] ?? null),
                'exif_depan_lg'          => $this->parseNumeric($r['exif_depan_lg'] ?? null),
                'exif_depan_distance_m'  => $this->parseNumeric($r['exif_depan_distance_m'] ?? null),
                'exif_dalam_la'          => $this->parseNumeric($r['exif_dalam_la'] ?? null),
                'exif_dalam_lg'          => $this->parseNumeric($r['exif_dalam_lg'] ?? null),
                'exif_dalam_distance_m'  => $this->parseNumeric($r['exif_dalam_distance_m'] ?? null),
                'is_exif_valid'          => $this->parseBool($r['is_exif_valid'] ?? null, true),
                'is_ktp_revised'         => $this->parseBool($r['is_ktp_revised'] ?? null, false),
                'ktp_revised_at'         => $this->parseDateTime($r['ktp_revised_at'] ?? null),
                'ktp_revised_by'         => $this->parseStr($r['ktp_revised_by'] ?? null),
                'ktp_unlocked_at'        => $this->parseDateTime($r['ktp_unlocked_at'] ?? null),
                'ktp_unlocked_by'        => $this->parseStr($r['ktp_unlocked_by'] ?? null),
                'reject_reason'          => $this->parseStr($r['reject_reason'] ?? null),
                'reset_reason'           => $this->parseStr($r['reset_reason'] ?? null),
                'is_ro'                  => $this->parseBool($r['is_ro'] ?? null, true),
                'previous_code_noo_principal' => $this->parseStr($r['previous_code_noo_principal'] ?? null),
            ];

            if (!$isDryRun) {
                DB::table('noo_submissions')->updateOrInsert(
                    ['request_id' => $reqId],
                    $recordData
                );
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        if (!$isDryRun) {
            DB::statement("SELECT setval('noo_submissions_id_seq', COALESCE((SELECT MAX(id) FROM noo_submissions), 1))");
        }

        $elapsed = round(microtime(true) - $startTime, 2);
        $this->info("🎉 MIGRASI SELESAI DALAM {$elapsed} DETIK!");
        $this->table(
            ['Metrik', 'Jumlah'],
            [
                ['Total Toko Diproses', $totalValid],
                ['Toko Terpetakan Foto Lokal', $matchedPhotoOutlets],
                ['Berkas Foto Disalin', $photosCopiedCount],
                ['Toko Menggunakan Link Drive', $unmatchedPhotoOutlets],
            ]
        );

        return Command::SUCCESS;
    }

    private function parseDateTime(?string $val): ?string
    {
        if ($val === null) return null;
        $val = trim($val);
        if ($val === '' || $val === 'NULL' || $val === '\N' || $val === '0' || $val === '0000-00-00 00:00:00' || str_starts_with($val, '1900-01-00')) return null;

        if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})(\s+(\d{1,2}):(\d{1,2})(:\d{1,2})?)?$/', $val, $m)) {
            $year = (int)$m[1];
            $month = (int)$m[2];
            $day = (int)$m[3];
            if ($month < 1 || $month > 12 || $day < 1 || $day > 31 || $year < 1970 || !checkdate($month, $day, $year)) {
                return null;
            }
            $hour = isset($m[5]) ? (int)$m[5] : 0;
            $min = isset($m[6]) ? (int)$m[6] : 0;
            $sec = isset($m[7]) ? (int)substr($m[7], 1) : 0;
            return sprintf('%04d-%02d-%02d %02d:%02d:%02d', $year, $month, $day, $hour, $min, $sec);
        }

        if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{2,4})(\s+(\d{1,2}):(\d{1,2})(:\d{1,2})?)?$/', $val, $m)) {
            $month = (int)$m[1];
            $day = (int)$m[2];
            $year = (int)$m[3];
            if ($year < 100) $year += 2000;
            if ($month < 1 || $month > 12 || $day < 1 || $day > 31 || $year < 1970 || !checkdate($month, $day, $year)) {
                return null;
            }
            $hour = isset($m[5]) ? (int)$m[5] : 0;
            $min = isset($m[6]) ? (int)$m[6] : 0;
            $sec = isset($m[7]) ? (int)substr($m[7], 1) : 0;
            return sprintf('%04d-%02d-%02d %02d:%02d:%02d', $year, $month, $day, $hour, $min, $sec);
        }

        try {
            $dt = new DateTime($val);
            $y = (int)$dt->format('Y');
            if ($y < 1970) return null;
            return $dt->format('Y-m-d H:i:s');
        } catch (Exception $e) {
            return null;
        }
    }

    private function parseBool(?string $val, ?bool $default = null): ?bool
    {
        if ($val === null) return $default;
        $val = strtolower(trim($val));
        if ($val === '' || $val === 'null' || $val === '\n') return $default;
        if (in_array($val, ['true', 't', '1', 'yes', 'y'])) return true;
        if (in_array($val, ['false', 'f', '0', 'no', 'n'])) return false;
        return $default;
    }

    private function parseNumeric(?string $val): ?float
    {
        if ($val === null) return null;
        $val = trim($val);
        if ($val === '' || $val === 'NULL' || $val === '\N') return null;
        if (!is_numeric($val)) return null;
        return (float)$val;
    }

    private function parseInt(?string $val, ?int $default = null): ?int
    {
        if ($val === null) return $default;
        $val = trim($val);
        if ($val === '' || $val === 'NULL' || $val === '\N') return $default;
        if (!is_numeric($val)) return $default;
        return (int)$val;
    }

    private function parseStr(?string $val): ?string
    {
        if ($val === null) return null;
        $val = trim($val);
        if ($val === '' || $val === 'NULL' || $val === '\N') return null;
        if (!mb_check_encoding($val, 'UTF-8')) {
            $val = mb_convert_encoding($val, 'UTF-8', 'Windows-1252, ISO-8859-1, UTF-8');
        }
        $val = iconv('UTF-8', 'UTF-8//IGNORE', $val);
        return $val;
    }
}
