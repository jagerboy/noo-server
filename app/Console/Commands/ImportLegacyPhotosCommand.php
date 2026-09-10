<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Throwable;

/**
 * Perintah Artisan untuk mengimpor foto NOO+ versi lama (Google Backup / Maret 2026 dst)
 * dengan pencocokan presisi (Kode Principal / Tanggal Submisi + Cabang).
 */
class ImportLegacyPhotosCommand extends Command
{
    protected $signature = 'noo:import-legacy-photos 
                            {path=D:\\AndroidStudioProjects\\NOO_PHOTOS_BACKUP_ORIGINAL : Path direktori backup foto}
                            {--dry-run : Simulasi pemindaian tanpa menyalin file atau mengubah database}
                            {--force : Timpa file foto jika sudah ada di direktori tujuan}';

    protected $description = 'Mengimpor file foto NOO versi lama (custcode principal / Google backup) ke storage lokal & database';

    public function handle(): int
    {
        $inputPath = $this->argument('path');
        $isDryRun = (bool) $this->option('dry-run');
        $isForce = (bool) $this->option('force');

        // Resolve absolute path
        $dirPath = realpath($inputPath) ?: base_path($inputPath);

        if (!is_dir($dirPath)) {
            $this->error("❌ Direktori tidak ditemukan: {$inputPath}");
            return Command::FAILURE;
        }

        $this->info("📂 Memindai berkas foto backup lama di: <comment>{$dirPath}</comment>" . ($isDryRun ? " (SIMULASI / DRY-RUN)" : ""));

        // Preload database submissions
        $this->line("⏳ Mengambil data submisi dari database...");
        try {
            $rawSubmissions = DB::table('noo_submissions')
                ->select(
                    'id',
                    'request_id',
                    'code_noo_principal',
                    'previous_code_noo_principal',
                    'custcode_distributor',
                    'branch_id',
                    'salesman_code',
                    'salesman_name',
                    'nama_noo',
                    'submitted_at',
                    'photo_depan_path',
                    'photo_dalam_path',
                    'photo_ktp_path'
                )
                ->orderBy('submitted_at', 'asc')
                ->orderBy('id', 'asc')
                ->get();
        } catch (Throwable $e) {
            $this->error("❌ Gagal membaca database noo_submissions: {$e->getMessage()}");
            $this->warn("💡 Pastikan service PostgreSQL sudah berjalan (misal: docker-compose up -d db)");
            return Command::FAILURE;
        }

        $this->info("🔍 Total data pengajuan di database: <info>{$rawSubmissions->count()}</info> toko.");

        if ($rawSubmissions->isEmpty()) {
            $this->warn("⚠️ Tabel noo_submissions di database masih kosong!");
            return Command::SUCCESS;
        }

        // Diagnostic table
        $this->newLine();
        $this->info("📋 Diagnostik Data Submisi di Database (10 Data Pertama):");
        $dbDiagTable = [];
        foreach ($rawSubmissions->take(10) as $sub) {
            $dbDiagTable[] = [
                'ID: ' . $sub->id,
                'Code: ' . ($sub->code_noo_principal ?? 'NULL'),
                'Branch: ' . ($sub->branch_id ?? '-'),
                'Date: ' . (!empty($sub->submitted_at) ? date('Y-m-d', strtotime((string)$sub->submitted_at)) : '-'),
                'Nama Toko: ' . mb_strimwidth((string)$sub->nama_noo, 0, 25, '...'),
                'Salesman: ' . ($sub->salesman_name ?? '-'),
            ];
        }
        $this->table(['ID DB', 'Kode Principal', 'Cabang', 'Tanggal', 'Nama Toko', 'Salesman'], $dbDiagTable);

        // Preload Lookup Table
        $submissionByCode = [];
        $submissionByBranchDate = [];

        foreach ($rawSubmissions as $sub) {
            if (!empty($sub->request_id)) {
                $submissionByCode[strtolower(trim((string)$sub->request_id))] = $sub;
            }
            if (!empty($sub->code_noo_principal)) {
                $submissionByCode[strtoupper(trim((string)$sub->code_noo_principal))] = $sub;
            }
            if (!empty($sub->previous_code_noo_principal)) {
                $submissionByCode[strtoupper(trim((string)$sub->previous_code_noo_principal))] = $sub;
            }
            if (!empty($sub->custcode_distributor)) {
                $submissionByCode[strtoupper(trim((string)$sub->custcode_distributor))] = $sub;
            }

            $bId = !empty($sub->branch_id) ? strtoupper(trim((string)$sub->branch_id)) : 'UNKNOWN';
            $dStr = !empty($sub->submitted_at) ? date('Y-m-d', strtotime((string)$sub->submitted_at)) : 'UNKNOWN';

            $submissionByBranchDate[$bId][$dStr][] = $sub;
        }

        // Pindai berkas foto secara rekursif
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dirPath, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        $scannedPhotos = [];

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if (!$file->isFile()) continue;

            $filename = $file->getFilename();
            $ext = strtolower($file->getExtension());

            if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
                continue;
            }

            $realPath = $file->getRealPath();

            $branchIdFromFolder = null;
            $dateFromFolder = null;
            $codeCandidate = null;
            $typeCandidate = null;

            if (preg_match('/(DEPAN|DALAM|KTP)/i', $filename, $mType)) {
                $typeCandidate = strtoupper($mType[1]);
            }

            if (preg_match('/BRANCH_([A-Z0-9]+)/i', $realPath, $mBranch)) {
                $branchIdFromFolder = strtoupper($mBranch[1]);
            }

            if (preg_match('/(\d{4}-\d{2}-\d{2})/', $realPath, $mDate)) {
                $dateFromFolder = $mDate[1];
            }

            if (preg_match('/([0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12})/i', $filename, $mCode)) {
                $codeCandidate = strtolower($mCode[1]);
            } elseif (preg_match('/^([A-Z0-9]{3,25})/i', $filename, $mCode) && !in_array(strtoupper($mCode[1]), ['DEPAN', 'DALAM', 'KTP', 'PHOTO', 'FOTO'])) {
                $codeCandidate = strtoupper($mCode[1]);
            } else {
                $parentFolder = basename(dirname($realPath));
                if (!in_array(strtoupper($parentFolder), ['DEPAN', 'DALAM', 'KTP', 'PHOTOS', 'FOTO', '05_PHOTOS'])) {
                    $codeCandidate = strtoupper($parentFolder);
                }
            }

            if (!$codeCandidate || !$typeCandidate) {
                continue;
            }

            $key = "{$branchIdFromFolder}|{$dateFromFolder}|{$codeCandidate}";
            $scannedPhotos[$key]['code'] = $codeCandidate;
            $scannedPhotos[$key]['branch'] = $branchIdFromFolder;
            $scannedPhotos[$key]['date'] = $dateFromFolder;
            $scannedPhotos[$key]['photos'][$typeCandidate] = $realPath;
        }

        $this->info("📸 Berhasil menemukan <info>" . count($scannedPhotos) . "</info> folder/grup foto outlet.");

        $matchedFiles = 0;
        $unmatchedFiles = [];
        $photosByRequestId = [];
        $claimedSubmissionIds = [];

        // PASSE 1: Direct Code Match (Match by request_id, code_noo_principal, previous_code_noo_principal, custcode_distributor)
        foreach ($scannedPhotos as $key => $data) {
            $code = $data['code'];
            $sub = $submissionByCode[$code] ?? null;

            if (!$sub) {
                $cleanCode = str_replace([' ', '_', '-'], '', $code);
                foreach ($submissionByCode as $k => $v) {
                    if (str_replace([' ', '_', '-'], '', $k) === $cleanCode) {
                        $sub = $v;
                        break;
                    }
                }
            }

            if ($sub) {
                $reqId = $sub->request_id;
                $claimedSubmissionIds[$sub->id] = true;
                $photosByRequestId[$reqId] = [
                    'sub' => $sub,
                    'code' => $code,
                    'photos' => $data['photos'],
                    'method' => 'Direct Code Match'
                ];
                $matchedFiles += count($data['photos']);
                unset($scannedPhotos[$key]);
            }
        }

        // PASSE 2: Exact Match by Branch + Date
        foreach ($scannedPhotos as $key => $data) {
            $code = $data['code'];
            $branch = $data['branch'];
            $date = $data['date'];

            $matchedSub = null;

            if ($branch && $date && isset($submissionByBranchDate[$branch][$date])) {
                foreach ($submissionByBranchDate[$branch][$date] as $candidateSub) {
                    if (!isset($claimedSubmissionIds[$candidateSub->id])) {
                        $matchedSub = $candidateSub;
                        break;
                    }
                }
            }

            if ($matchedSub) {
                $reqId = $matchedSub->request_id;
                $claimedSubmissionIds[$matchedSub->id] = true;
                $photosByRequestId[$reqId] = [
                    'sub' => $matchedSub,
                    'code' => $code,
                    'photos' => $data['photos'],
                    'method' => 'Branch + Tanggal Presisi'
                ];
                $matchedFiles += count($data['photos']);
                unset($scannedPhotos[$key]);
            } else {
                $unmatchedFiles[] = "{$code} (Cabang: {$branch}, Tgl: {$date})";
            }
        }

        $totalOutlets = count($photosByRequestId);
        $unmatchedCount = count($unmatchedFiles);

        $this->newLine();
        $this->info("✅ Berhasil memetakan: <info>{$matchedFiles}</info> file foto untuk <info>{$totalOutlets}</info> toko.");
        if ($unmatchedCount > 0) {
            $this->warn("⚠️ Ada {$unmatchedCount} grup foto yang belum cocok (karena data submisi di DB lokal belum lengkap).");
        }

        if ($isDryRun) {
            $this->newLine();
            $this->info("✅ [DRY-RUN SELESAI]");
            $this->line("   Total File Foto Valid : {$matchedFiles}");
            $this->line("   Total Toko Terkait    : {$totalOutlets}");
            $this->line("   Foto Belum Terhubung  : {$unmatchedCount}");

            if (!empty($photosByRequestId)) {
                $this->newLine();
                $this->info("📋 Daftar Toko Yang Presisi Terhubung:");
                $sampleTable = [];
                foreach ($photosByRequestId as $reqId => $item) {
                    $s = $item['sub'];
                    $sampleTable[] = [
                        $item['code'],
                        $s->nama_noo,
                        $s->branch_id,
                        date('Y-m-d', strtotime((string)$s->submitted_at)),
                        $item['method'],
                        implode(', ', array_keys($item['photos']))
                    ];
                }
                $this->table(['Kode Folder', 'Nama Toko (DB)', 'Cabang DB', 'Tgl Submisi', 'Metode Match', 'Foto'], $sampleTable);
            }
            return Command::SUCCESS;
        }

        // Eksekusi penyalinan file & pembaruan database
        $this->line("⏳ Menyalin file foto ke storage server (storage/app/public/noo_photos) & memperbarui database...");

        $bar = $this->output->createProgressBar($totalOutlets);
        $bar->start();

        $updatedOutlets = 0;

        foreach ($photosByRequestId as $requestId => $item) {
            $sub = $item['sub'];
            $code = $item['code'];
            $photos = $item['photos'];

            $branchId = !empty($sub->branch_id) ? $sub->branch_id : 'UNKNOWN';
            $dateFolder = !empty($sub->submitted_at) ? date('Y-m-d', strtotime((string)$sub->submitted_at)) : '2026-01-01';

            $updateData = [
                'photo_status' => 'COMPLETED',
                'updated_at' => now(),
            ];

            if (empty($sub->code_noo_principal) && !empty($code)) {
                $updateData['code_noo_principal'] = $code;
            }

            foreach ($photos as $type => $sourcePath) {
                $relativeTarget = "noo_photos/{$branchId}/{$dateFolder}/{$requestId}_{$type}.jpg";
                $absoluteTarget = storage_path("app/public/{$relativeTarget}");

                $destDir = dirname($absoluteTarget);
                if (!is_dir($destDir)) {
                    File::makeDirectory($destDir, 0775, true, true);
                }

                if ($isForce || !file_exists($absoluteTarget)) {
                    @copy($sourcePath, $absoluteTarget);
                }

                if ($type === 'DEPAN') $updateData['photo_depan_path'] = $relativeTarget;
                if ($type === 'DALAM') $updateData['photo_dalam_path'] = $relativeTarget;
                if ($type === 'KTP')   $updateData['photo_ktp_path']   = $relativeTarget;
            }

            DB::table('noo_submissions')->where('request_id', $requestId)->update($updateData);

            $updatedOutlets++;
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->info("🎉 MIGRASI FOTO LAMA SUKSES!");
        $this->table(
            ['Metrik', 'Jumlah'],
            [
                ['Total File Foto Diproses', $matchedFiles],
                ['Total Toko Diperbarui', $updatedOutlets],
                ['Grup Foto Tidak Terkait DB', $unmatchedCount],
            ]
        );

        return Command::SUCCESS;
    }
}
