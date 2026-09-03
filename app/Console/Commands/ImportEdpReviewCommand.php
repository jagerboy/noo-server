<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\NooStatusEnum;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Perintah Artisan untuk mengimpor data riwayat/migrasi dari Google Sheet NOO_EDP_REVIEW
 * ke dalam tabel PostgreSQL noo_submissions.
 */
class ImportEdpReviewCommand extends Command
{
    /**
     * Nama dan tanda tangan perintah console.
     *
     * @var string
     */
    protected $signature = 'noo:import-edp-review 
                            {file : Path file CSV, TSV, atau TXT export sheet NOO_EDP_REVIEW}
                            {--delimiter= : Delimiter khusus (contoh: tab, comma, semicolon). Jika kosong akan dideteksi otomatis}
                            {--dry-run : Menjalankan simulasi validasi tanpa menyimpan ke database}';

    /**
     * Deskripsi perintah console.
     *
     * @var string
     */
    protected $description = 'Mengimpor data sheet NOO_EDP_REVIEW ke dalam tabel noo_submissions';

    public function handle(): int
    {
        $filePath = $this->argument('file');
        $isDryRun = (bool) $this->option('dry-run');

        if (!file_exists($filePath)) {
            $this->error("❌ Berkas tidak ditemukan: {$filePath}");
            return Command::FAILURE;
        }

        $this->info("📂 Membaca file: {$filePath}" . ($isDryRun ? " (SIMULASI / DRY-RUN)" : ""));

        $delimiter = $this->detectDelimiter($filePath, $this->option('delimiter'));
        $delimiterName = $delimiter === "\t" ? 'TAB (\t)' : ($delimiter === ';' ? 'SEMICOLON (;)' : 'COMMA (,)');
        $this->line("⚙️  Menggunakan delimiter: <comment>{$delimiterName}</comment>");

        $handle = fopen($filePath, 'r');
        if (!$handle) {
            $this->error("❌ Gagal membuka berkas.");
            return Command::FAILURE;
        }

        // Cari baris header yang sebenarnya (yang mengandung 'request_id')
        $headers = [];
        $headerLineFound = false;

        while (($rawHeader = fgetcsv($handle, 8192, $delimiter)) !== false) {
            $cleaned = array_map(function ($h) {
                $clean = preg_replace('/[\x00-\x1F\x7F-\xFF]/', '', (string)$h);
                return strtolower(trim($clean));
            }, $rawHeader);

            if (in_array('request_id', $cleaned)) {
                $headers = $cleaned;
                $headerLineFound = true;
                break;
            }
        }

        if (!$headerLineFound || empty($headers)) {
            $this->error("❌ Header 'request_id' tidak ditemukan di dalam berkas CSV.");
            fclose($handle);
            return Command::FAILURE;
        }

        $this->info("🔍 Header ditemukan: " . implode(', ', array_slice(array_filter($headers), 0, 8)) . "... (" . count($headers) . " kolom)");

        $insertedCount = 0;
        $updatedCount = 0;
        $skippedCount = 0;
        $rowNumber = 1;

        $branchMap = [];
        if (!$isDryRun) {
            DB::beginTransaction();
            try {
                $branchMap = DB::table('master_branches')->get()->keyBy('branch_id')->toArray();
            } catch (Throwable) {
                // Ignore if DB connection fails in dry run or initial setup
            }
        }

        try {
            while (($row = fgetcsv($handle, 8192, $delimiter)) !== false) {
                $rowNumber++;

                // Lewati baris kosong
                if (empty(array_filter($row, fn($val) => trim((string)$val) !== ''))) {
                    continue;
                }

                // Petakan data baris ke array key-value berdasarkan header
                $data = [];
                foreach ($headers as $idx => $headerName) {
                    $data[$headerName] = isset($row[$idx]) ? trim((string)$row[$idx]) : '';
                }

                $rawRequestId = trim($data['request_id'] ?? '');

                // Validasi format UUID untuk PostgreSQL
                if (empty($rawRequestId) || strlen($rawRequestId) < 10 || !\Illuminate\Support\Str::isUuid($rawRequestId)) {
                    if (empty($rawRequestId) || strlen($rawRequestId) < 3) {
                        $this->warn("⚠️ Baris {$rowNumber}: 'request_id' kosong/invalid ('{$rawRequestId}'), dibuatkan UUID baru.");
                        $data['request_id'] = (string) \Illuminate\Support\Str::uuid();
                    } else {
                        // Jika format UUID sedikit cacat, generate UUID valid dan simpan aslinya di flags
                        $newUuid = (string) \Illuminate\Support\Str::uuid();
                        $data['flags'] = ($data['flags'] ?? '') . " [LegacyID: {$rawRequestId}]";
                        $data['request_id'] = $newUuid;
                    }
                }

                $requestId = $data['request_id'];

                try {
                    // Parse & susun record untuk tabel noo_submissions
                    $record = $this->transformRowToSubmission($data, $branchMap);

                    if ($isDryRun) {
                        $insertedCount++;
                        continue;
                    }

                    // Pastikan master_branches memiliki branch_id ini untuk mencegah foreign key error
                    if (!isset($branchMap[$record['branch_id']])) {
                        $this->ensureBranchExists($record['branch_id'], $record['branch_name'] ?? '');
                        $branchMap[$record['branch_id']] = (object)['region_code' => 'SUMATERA', 'area_code' => 'SUM1'];
                    }

                    // Cek apakah request_id sudah ada
                    $existing = DB::table('noo_submissions')->where('request_id', $requestId)->first();

                    if ($existing) {
                        $updateData = $record;
                        unset($updateData['request_id']);
                        $updateData['updated_at'] = now();

                        DB::table('noo_submissions')->where('request_id', $requestId)->update($updateData);
                        $updatedCount++;
                    } else {
                        $record['created_at'] = now();
                        $record['updated_at'] = now();

                        DB::table('noo_submissions')->insert($record);
                        $insertedCount++;
                    }
                } catch (Throwable $rowError) {
                    $this->warn("⚠️ Baris {$rowNumber} dilewati: {$rowError->getMessage()}");
                    $skippedCount++;
                }
            }

            fclose($handle);

            if ($isDryRun) {
                $this->info("\n✅ [DRY-RUN SELESAI] Data valid!");
                $this->line("   Total baris diproses: {$insertedCount}");
                $this->line("   Total baris dilewati: {$skippedCount}");
                return Command::SUCCESS;
            }

            DB::commit();

            // Sinkronisasi PostgreSQL Auto-Increment Sequence
            $this->syncPostgresSequences();

            $this->newLine();
            $this->info("🎉 MIGRASI SUKSES!");
            $this->table(
                ['Status', 'Jumlah Baris'],
                [
                    ['Data Baru (Inserted)', $insertedCount],
                    ['Data Diperbarui (Updated)', $updatedCount],
                    ['Dilewati (Skipped/Invalid)', $skippedCount],
                    ['Total Berhasil', $insertedCount + $updatedCount],
                ]
            );

            return Command::SUCCESS;
        } catch (Throwable $e) {
            if (!$isDryRun) {
                DB::rollBack();
            }
            fclose($handle);
            $this->error("❌ Terjadi kesalahan pada baris {$rowNumber}: {$e->getMessage()}");
            $this->line($e->getTraceAsString());
            return Command::FAILURE;
        }
    }

    /**
     * Konversi data 1 baris sheet NOO_EDP_REVIEW menjadi struktur kolom noo_submissions.
     */
    protected function transformRowToSubmission(array $d, array $branchMap = []): array
    {
        $branchId = $d['branch_id'] ?? 'DAPLG002';
        $branchName = $d['branch_name'] ?? '';

        // Ambil data region dari branch master jika sudah ada
        $branchMaster = $branchMap[$branchId] ?? null;
        $regionCode = $branchMaster ? ($branchMaster->region_code ?? 'SUMATERA') : 'SUMATERA';
        $areaCode = $branchMaster ? ($branchMaster->area_code ?? null) : null;

        // Parse format tanggal
        $submittedAt = $this->parseDateTime($d['submitted_at'] ?? '') ?? now();
        $edpReviewedAt = $this->parseDateTime($d['edp_reviewed_at'] ?? '');
        $injectedAt = $this->parseDateTime($d['injected_at'] ?? '');

        // Koordinat GPS
        $la = !empty($d['la']) ? (float) $d['la'] : 0.0;
        $lg = !empty($d['lg']) ? (float) $d['lg'] : 0.0;
        $accuracy = !empty($d['accuracy_m']) ? (float) $d['accuracy_m'] : null;

        // Keputusan EDP & Approval SPV
        $edpDecision = !empty($d['edp_decision']) ? $d['edp_decision'] : null;
        $injectStatus = !empty($d['inject_status']) ? strtoupper($d['inject_status']) : null;
        $approvalSpv = !empty($d['approval_spv_area']) ? strtoupper($d['approval_spv_area']) : null;
        $spvInjectedBy = !empty($d['spv_injected_by']) ? $d['spv_injected_by'] : null;
        $injectedBy = !empty($d['injected_by']) ? $d['injected_by'] : null;

        // Tentukan status workflow di database
        $status = $this->determineStatus($edpDecision, $injectStatus, $approvalSpv, $d['custcode_distributor'] ?? '');

        // Flags & URL Foto lama
        $photoUrl = $d['photo_url'] ?? '';
        $flags = $d['flags'] ?? '';
        if (!empty($photoUrl) && !str_contains($flags, $photoUrl)) {
            $flags = empty($flags) ? "DriveFolder: {$photoUrl}" : "{$flags} | DriveFolder: {$photoUrl}";
        }

        return [
            'request_id' => $d['request_id'],
            'submitted_at' => $submittedAt,
            'principal' => 'ASWFOODS',
            'principal_code' => 'A',
            'region_code' => $regionCode,
            'branch_id' => $branchId,
            'branch_name' => $branchName,
            'area_code' => $areaCode,
            'salesman_code' => $d['salesman_code'] ?? '',
            'salesman_name' => $d['salesman_name'] ?? '',
            'code_noo_principal' => !empty($d['code_noo_principal']) ? $d['code_noo_principal'] : null,
            'custcode_distributor' => !empty($d['custcode_distributor']) ? $d['custcode_distributor'] : null,
            'nama_noo' => $d['nama_noo'] ?? '',
            'alamat_noo' => $d['alamat_noo'] ?? '',
            'kel_noo' => $d['kel_noo'] ?? null,
            'kec_noo' => $d['kec_noo'] ?? null,
            'kab_kota_noo' => $d['kab_kota_noo'] ?? null,
            'provinsi_noo' => $d['provinsi_noo'] ?? null,
            'type_outlet_code' => $d['type_outlet_code'] ?? 'GT04',
            'type_outlet_desc' => $d['type_outlet_desc'] ?? 'RETAIL',
            'la' => $la,
            'lg' => $lg,
            'accuracy_m' => $accuracy,
            'locked_la' => $la,
            'locked_lg' => $lg,
            'locked_accuracy_m' => $accuracy,
            'samples_count' => 10,
            'sampling_interval_sec' => 1,
            'geo_duration_sec' => 30,
            'photo_status' => !empty($photoUrl) ? 'PROGRESS' : 'PENDING',
            'norute' => !empty($d['norute']) ? $d['norute'] : null,
            'h1' => !empty($d['h1']) ? $d['h1'] : null,
            'h2' => !empty($d['h2']) ? $d['h2'] : null,
            'h3' => !empty($d['h3']) ? $d['h3'] : null,
            'h4' => !empty($d['h4']) ? $d['h4'] : null,
            'h5' => !empty($d['h5']) ? $d['h5'] : null,
            'h6' => !empty($d['h6']) ? $d['h6'] : null,
            'h7' => !empty($d['h7']) ? $d['h7'] : null,
            'm1' => !empty($d['m1']) ? $d['m1'] : null,
            'm2' => !empty($d['m2']) ? $d['m2'] : null,
            'm3' => !empty($d['m3']) ? $d['m3'] : null,
            'm4' => !empty($d['m4']) ? $d['m4'] : null,
            'spv_notes' => !empty($d['spv_notes']) ? $d['spv_notes'] : null,
            'approval_spv_area' => $approvalSpv,
            'approved_by_spv' => $spvInjectedBy,
            'spv_submit_at' => ($approvalSpv === 'YES') ? ($edpReviewedAt ?? $submittedAt) : null,
            'pushed_to_edp_at' => ($approvalSpv === 'YES') ? ($edpReviewedAt ?? $submittedAt) : null,
            'pushed_to_spv_at' => !empty($d['custcode_distributor']) ? $submittedAt : null,
            'edp_decision' => $edpDecision,
            'edp_notes' => !empty($d['edp_notes']) ? $d['edp_notes'] : null,
            'edp_reviewed_at' => $edpReviewedAt,
            'inject_status' => $injectStatus,
            'injected_at' => $injectedAt,
            'injected_by' => $injectedBy,
            'approved_by_edp' => $injectedBy,
            'flags' => !empty($flags) ? $flags : null,
            'status' => $status,
        ];
    }

    /**
     * Logika penentuan status workflow berdasarkan histori review sheet.
     */
    protected function determineStatus(?string $edpDecision, ?string $injectStatus, ?string $approvalSpv, string $custCode): string
    {
        if (strcasecmp((string)$edpDecision, 'Approved') === 0 || in_array($injectStatus, ['READY', 'DONE', 'INJECTED'])) {
            return NooStatusEnum::APPROVED_EDP->value;
        }

        if (strcasecmp((string)$edpDecision, 'Rejected') === 0 || strcasecmp((string)$edpDecision, 'Returned') === 0) {
            return NooStatusEnum::REJECTED_EDP->value;
        }

        if ($approvalSpv === 'YES') {
            return NooStatusEnum::APPROVED_SPV->value;
        }

        if ($approvalSpv === 'NO') {
            return NooStatusEnum::REJECTED_SPV->value;
        }

        if (!empty($custCode)) {
            return NooStatusEnum::PUSHED_TO_SPV->value;
        }

        return NooStatusEnum::SE_SUBMITTED->value;
    }

    /**
     * Memastikan branch_id terdaftar di tabel master_branches agar tidak melanggar foreign key constraint.
     */
    protected function ensureBranchExists(string $branchId, string $branchName): void
    {
        $exists = DB::table('master_branches')->where('branch_id', $branchId)->exists();
        if (!$exists) {
            DB::table('master_branches')->insert([
                'region_code' => 'SUMATERA',
                'region_name' => 'SUMATERA',
                'principal_code' => 'A',
                'principal_name' => 'ASWFOODS',
                'entity_code_principal' => 'ASW',
                'entity_name_principal' => 'PT ASIA SAKTI WAHID FOODS MANUFACTURE',
                'area_code' => 'SUM1',
                'branch_id' => $branchId,
                'branch_name' => !empty($branchName) ? $branchName : $branchId,
                'pin_branch' => '123456',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Parsing berbagai kemungkinan format string tanggal ke format standar PostgreSQL (Y-m-d H:i:s).
     */
    protected function parseDateTime(?string $dateStr): ?string
    {
        if (empty($dateStr)) {
            return null;
        }

        $dateStr = trim($dateStr);

        try {
            // Coba parsing standar Carbon
            return Carbon::parse($dateStr)->format('Y-m-d H:i:s');
        } catch (Throwable $e) {
            // Coba format spesifik: m/d/Y H:i:s atau d/m/Y H:i:s
            $formats = [
                'n/j/Y H:i:s',
                'm/d/Y H:i:s',
                'd/m/Y H:i:s',
                'Y-m-d H:i:s',
                'Y-m-d',
                'd-m-Y H:i:s',
                'd-m-Y',
            ];

            foreach ($formats as $fmt) {
                try {
                    return Carbon::createFromFormat($fmt, $dateStr)->format('Y-m-d H:i:s');
                } catch (Throwable) {
                    continue;
                }
            }
        }

        return null;
    }

    /**
     * Mendeteksi delimiter CSV / TSV / Semicolon secara otomatis dari baris pertama.
     */
    protected function detectDelimiter(string $filePath, ?string $customOption): string
    {
        if (!empty($customOption)) {
            return match (strtolower($customOption)) {
                'tab', '\t', 'tsv' => "\t",
                'semicolon', ';' => ';',
                default => ',',
            };
        }

        $firstLine = file_get_contents($filePath, false, null, 0, 4096);
        if (!$firstLine) {
            return ',';
        }

        $firstLine = explode("\n", $firstLine)[0];

        $tabCount = substr_count($firstLine, "\t");
        $commaCount = substr_count($firstLine, ",");
        $semicolonCount = substr_count($firstLine, ";");

        if ($tabCount > $commaCount && $tabCount > $semicolonCount) {
            return "\t";
        }

        if ($semicolonCount > $commaCount && $semicolonCount > $tabCount) {
            return ';';
        }

        return ',';
    }

    /**
     * Sinkronisasi auto-increment sequences PostgreSQL.
     */
    protected function syncPostgresSequences(): void
    {
        try {
            DB::statement("SELECT setval('noo_submissions_id_seq', COALESCE((SELECT MAX(id) FROM noo_submissions), 1))");
        } catch (Throwable $e) {
            // Ignore jika sequence berbeda atau tabel kosong
        }
    }
}
