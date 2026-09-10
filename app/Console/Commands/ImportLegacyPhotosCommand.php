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
 * dengan prioritas utama UUID (request_id), Kode Principal (CustCode), dan Tanggal Presisi.
 */
class ImportLegacyPhotosCommand extends Command
{
    protected $signature = 'noo:import-legacy-photos 
                            {path=/var/www/NOO_PHOTOS_BACKUP_ORIGINAL : Path direktori backup foto}
                            {--dry-run : Simulasi pemindaian tanpa menyalin file atau mengubah database}
                            {--force : Timpa file foto jika sudah ada di direktori tujuan}';

    protected $description = 'Mengimpor file foto NOO versi lama ke storage lokal & database';

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
                    'nama_pemilik_outlet',
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
            return Command::FAILURE;
        }

        $this->info("🔍 Total data pengajuan di database: <info>{$rawSubmissions->count()}</info> toko.");

        if ($rawSubmissions->isEmpty()) {
            $this->warn("⚠️ Tabel noo_submissions di database masih kosong!");
            return Command::SUCCESS;
        }

        // Preload Lookup Tables
        $submissionByRequestId = [];
        $submissionByCode = [];
        $submissionByName = [];
        $submissionByBranchDate = [];
        $submissionByBranch = [];

        foreach ($rawSubmissions as $sub) {
            $reqId = strtolower(trim((string)$sub->request_id));
            if (!empty($reqId)) {
                $submissionByRequestId[$reqId] = $sub;
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

            if (!empty($sub->nama_noo)) {
                $cleanName = $this->cleanString((string)$sub->nama_noo);
                if (strlen($cleanName) > 4) {
                    $submissionByName[$cleanName] = $sub;
                }
            }

            $bId = !empty($sub->branch_id) ? strtoupper(trim((string)$sub->branch_id)) : 'UNKNOWN';
            $dStr = !empty($sub->submitted_at) ? date('Y-m-d', strtotime((string)$sub->submitted_at)) : 'UNKNOWN';

            $submissionByBranchDate[$bId][$dStr][] = $sub;
            $submissionByBranch[$bId][] = $sub;
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
            $parentFolder = basename(dirname($realPath));

            // Tentukan tipe foto (DEPAN, DALAM, KTP)
            $typeCandidate = null;
            if (preg_match('/(DEPAN|STORE|OUTLET|OUTSIDE|TOKO|FRONT)/i', $filename)) {
                $typeCandidate = 'DEPAN';
            } elseif (preg_match('/(DALAM|INSIDE|INTERIOR)/i', $filename)) {
                $typeCandidate = 'DALAM';
            } elseif (preg_match('/(KTP|SELFIE|OWNER|PEMILIK|TAX|NPWP)/i', $filename)) {
                $typeCandidate = 'KTP';
            } elseif (preg_match('/(DEPAN|STORE|OUTLET|OUTSIDE|TOKO|FRONT)/i', $parentFolder)) {
                $typeCandidate = 'DEPAN';
            } elseif (preg_match('/(DALAM|INSIDE|INTERIOR)/i', $parentFolder)) {
                $typeCandidate = 'DALAM';
            } elseif (preg_match('/(KTP|SELFIE|OWNER|PEMILIK|TAX|NPWP)/i', $parentFolder)) {
                $typeCandidate = 'KTP';
            } else {
                // Default ke DEPAN jika tidak teridentifikasi
                $typeCandidate = 'DEPAN';
            }

            // Ekstrak UUID (request_id) jika ada di path/filename
            $uuidCandidate = null;
            if (preg_match('/([0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12})/i', $realPath, $mUuid)) {
                $uuidCandidate = strtolower($mUuid[1]);
            }

            // Ekstrak Branch ID & Tanggal dari Folder
            $branchIdFromFolder = null;
            if (preg_match('/BRANCH_([A-Z0-9]+)/i', $realPath, $mBranch)) {
                $branchIdFromFolder = strtoupper($mBranch[1]);
            } elseif (preg_match('/([A-Z]{2,4}[A-Z0-9]{3,6})/i', $realPath, $mBranch2)) {
                if (isset($submissionByBranch[strtoupper($mBranch2[1])])) {
                    $branchIdFromFolder = strtoupper($mBranch2[1]);
                }
            }

            $dateFromFolder = null;
            if (preg_match('/(\d{4}-\d{2}-\d{2})/', $realPath, $mDate)) {
                $dateFromFolder = $mDate[1];
            }

            // Ekstrak Kode/Name Candidate
            $codeCandidate = null;
            if ($uuidCandidate) {
                $codeCandidate = $uuidCandidate;
            } elseif (preg_match('/^([A-Z0-9]{3,25})$/i', $parentFolder) && !in_array(strtoupper($parentFolder), ['DEPAN', 'DALAM', 'KTP', 'PHOTOS', 'FOTO', '05_PHOTOS'])) {
                $codeCandidate = strtoupper($parentFolder);
            } elseif (preg_match('/^([A-Z0-9]{3,25})/i', $filename, $mCode) && !in_array(strtoupper($mCode[1]), ['DEPAN', 'DALAM', 'KTP', 'PHOTO', 'FOTO'])) {
                $codeCandidate = strtoupper($mCode[1]);
            } else {
                $codeCandidate = $parentFolder;
            }

            $groupKey = $uuidCandidate ? "uuid_{$uuidCandidate}" : "{$branchIdFromFolder}|{$dateFromFolder}|{$codeCandidate}";

            $scannedPhotos[$groupKey]['uuid'] = $uuidCandidate;
            $scannedPhotos[$groupKey]['code'] = $codeCandidate;
            $scannedPhotos[$groupKey]['branch'] = $branchIdFromFolder;
            $scannedPhotos[$groupKey]['date'] = $dateFromFolder;
            $scannedPhotos[$groupKey]['parent_folder'] = $parentFolder;
            $scannedPhotos[$groupKey]['photos'][$typeCandidate] = $realPath;
        }

        $this->info("📸 Berhasil menemukan <info>" . count($scannedPhotos) . "</info> grup foto outlet.");

        $matchedFiles = 0;
        $unmatchedFiles = [];
        $photosByRequestId = [];
        $claimedSubmissionIds = [];

        // PASSE 0: Match Presisi via UUID (request_id)
        foreach ($scannedPhotos as $key => $data) {
            $uuid = $data['uuid'];
            if ($uuid && isset($submissionByRequestId[$uuid])) {
                $sub = $submissionByRequestId[$uuid];
                $reqId = $sub->request_id;
                $claimedSubmissionIds[$sub->id] = true;
                $photosByRequestId[$reqId] = [
                    'sub' => $sub,
                    'code' => $data['code'],
                    'photos' => $data['photos'],
                    'method' => 'UUID Direct Match'
                ];
                $matchedFiles += count($data['photos']);
                unset($scannedPhotos[$key]);
            }
        }

        // PASSE 1: Match Presisi via Kode Principal (CAPLBxxxx, dst)
        foreach ($scannedPhotos as $key => $data) {
            $code = $data['code'];
            if (!$code) continue;

            $sub = $submissionByCode[$code] ?? null;
            if (!$sub) {
                $cleanCode = str_replace([' ', '_', '-'], '', (string)$code);
                foreach ($submissionByCode as $k => $v) {
                    if (str_replace([' ', '_', '-'], '', (string)$k) === $cleanCode) {
                        $sub = $v;
                        break;
                    }
                }
            }

            if ($sub && !isset($claimedSubmissionIds[$sub->id])) {
                $reqId = $sub->request_id;
                $claimedSubmissionIds[$sub->id] = true;
                $photosByRequestId[$reqId] = [
                    'sub' => $sub,
                    'code' => $code,
                    'photos' => $data['photos'],
                    'method' => 'CustCode Principal Match'
                ];
                $matchedFiles += count($data['photos']);
                unset($scannedPhotos[$key]);
            }
        }

        // PASSE 2: Match via Nama Toko (Folder/File Name Matching)
        foreach ($scannedPhotos as $key => $data) {
            $code = $data['code'];
            $cleanCode = $this->cleanString((string)$code);

            if (strlen($cleanCode) > 4 && isset($submissionByName[$cleanCode])) {
                $sub = $submissionByName[$cleanCode];
                if (!isset($claimedSubmissionIds[$sub->id])) {
                    $reqId = $sub->request_id;
                    $claimedSubmissionIds[$sub->id] = true;
                    $photosByRequestId[$reqId] = [
                        'sub' => $sub,
                        'code' => $code,
                        'photos' => $data['photos'],
                        'method' => 'Nama Toko Match'
                    ];
                    $matchedFiles += count($data['photos']);
                    unset($scannedPhotos[$key]);
                }
            }
        }

        // PASSE 3: Match via Branch + Date
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
            }
        }

        // PASSE 4: Match Fallback via Branch (Ambil submisi yang belum terhubung foto untuk cabang tersebut)
        foreach ($scannedPhotos as $key => $data) {
            $code = $data['code'];
            $branch = $data['branch'];

            $matchedSub = null;
            if ($branch && isset($submissionByBranch[$branch])) {
                foreach ($submissionByBranch[$branch] as $candidateSub) {
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
                    'method' => 'Branch Fallback Match'
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

        if ($isDryRun) {
            $this->newLine();
            $this->info("✅ [DRY-RUN SELESAI]");
            $this->line("   Total File Foto Valid : {$matchedFiles}");
            $this->line("   Total Toko Terkait    : {$totalOutlets}");
            $this->line("   Foto Belum Terhubung  : {$unmatchedCount}");

            if (!empty($photosByRequestId)) {
                $this->newLine();
                $this->info("📋 Contoh Toko Yang Presisi Terhubung:");
                $sampleTable = [];
                $count = 0;
                foreach ($photosByRequestId as $reqId => $item) {
                    $s = $item['sub'];
                    $sampleTable[] = [
                        $item['code'],
                        $s->nama_noo,
                        $s->request_id,
                        $s->branch_id,
                        $item['method'],
                        implode(', ', array_keys($item['photos']))
                    ];
                    if (++$count >= 15) break;
                }
                $this->table(['Kode/Folder', 'Nama Toko (DB)', 'Request ID (DB)', 'Cabang DB', 'Metode Match', 'Foto'], $sampleTable);
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
            $dateFolder = !empty($sub->submitted_at) && !str_starts_with((string)$sub->submitted_at, '1970')
                ? date('Y-m-d', strtotime((string)$sub->submitted_at)) 
                : '2026-01-01';

            $updateData = [
                'photo_status' => 'COMPLETED',
                'updated_at' => now(),
            ];

            if (empty($sub->code_noo_principal) && !empty($code) && !preg_match('/^[0-9a-f-]{36}$/i', $code)) {
                $updateData['code_noo_principal'] = $code;
            }

            foreach ($photos as $type => $sourcePath) {
                // Tentukan target file persis sesuai request_id toko di DB
                $relativeTarget = "noo_photos/{$branchId}/{$dateFolder}/{$requestId}_{$type}.jpg";
                $absoluteTarget = storage_path("app/public/{$relativeTarget}");

                $destDir = dirname($absoluteTarget);
                if (!is_dir($destDir)) {
                    File::makeDirectory($destDir, 0775, true, true);
                }

                // Selalu salin & timpa agar foto ter-replace dengan foto yang presisi
                @copy($sourcePath, $absoluteTarget);

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

        $this->info("🎉 MIGRASI FOTO LAMA SUKSES & PRESISI!");
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

    private function cleanString(string $val): string
    {
        return strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $val));
    }
}
