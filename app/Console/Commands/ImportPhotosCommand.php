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
 * Perintah Artisan untuk mengimpor dan menata berkas foto dari Google Drive (folder 05_PHOTOS)
 * ke dalam storage lokal server (storage/app/public/noo_photos) dan menautkannya ke tabel noo_submissions.
 */
class ImportPhotosCommand extends Command
{
    protected $signature = 'noo:import-photos 
                            {path=05_PHOTOS : Path direktori folder 05_PHOTOS}
                            {--dry-run : Simulasi pemindaian tanpa menyalin file atau mengubah database}
                            {--force : Timpa file foto jika sudah ada di direktori tujuan}';

    protected $description = 'Mengimpor file foto toko dari Google Drive (05_PHOTOS) ke storage lokal dan database';

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

        $this->info("📂 Memindai berkas foto di: <comment>{$dirPath}</comment>" . ($isDryRun ? " (SIMULASI / DRY-RUN)" : ""));

        // Preload mapping request_id -> [branch_id, submitted_at] dari tabel noo_submissions
        $this->line("⏳ Mengambil data submisi dari database...");
        try {
            $submissions = DB::table('noo_submissions')
                ->select('request_id', 'branch_id', 'submitted_at', 'photo_depan_path', 'photo_dalam_path', 'photo_ktp_path')
                ->get()
                ->keyBy('request_id');
        } catch (Throwable $e) {
            $this->error("❌ Gagal membaca database noo_submissions: {$e->getMessage()}");
            return Command::FAILURE;
        }

        $this->info("🔍 Total data pengajuan di database: <info>{$submissions->count()}</info> toko.");

        // Telusuri direktori secara rekursif
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dirPath, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        $matchedFiles = 0;
        $unmatchedFiles = 0;
        $photosByRequest = []; // [request_id => ['DEPAN' => path, 'DALAM' => path, 'KTP' => path]]

        $this->line("⏳ Memproses seluruh subfolder foto...");

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if (!$file->isFile()) continue;

            $filename = $file->getFilename();
            $ext = strtolower($file->getExtension());

            if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
                continue;
            }

            // Pola format nama file: {request_id}_{TIPE}.jpg
            // Contoh: 8e32f491-b385-4c61-ad68-03e5c42d073a_DEPAN.jpg
            if (preg_match('/^([0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12})_(DEPAN|DALAM|KTP)\.(jpg|jpeg|png|webp)$/i', $filename, $matches)) {
                $requestId = strtolower($matches[1]);
                $type = strtoupper($matches[2]);

                if (!isset($submissions[$requestId])) {
                    $unmatchedFiles++;
                    continue;
                }

                $sub = $submissions[$requestId];
                $branchId = !empty($sub->branch_id) ? $sub->branch_id : 'UNKNOWN';
                $dateFolder = !empty($sub->submitted_at) ? date('Y-m-d', strtotime((string)$sub->submitted_at)) : '2026-01-01';

                // Target relatif di public storage
                $relativeTarget = "noo_photos/{$branchId}/{$dateFolder}/{$requestId}_{$type}.jpg";
                $absoluteTarget = storage_path("app/public/{$relativeTarget}");

                $photosByRequest[$requestId][$type] = [
                    'source' => $file->getRealPath(),
                    'relative_target' => $relativeTarget,
                    'absolute_target' => $absoluteTarget,
                ];

                $matchedFiles++;
            }
        }

        $totalOutlets = count($photosByRequest);
        $this->info("✅ Berhasil memetakan: <info>{$matchedFiles}</info> foto untuk <info>{$totalOutlets}</info> toko.");
        if ($unmatchedFiles > 0) {
            $this->warn("⚠️ Ditemukan {$unmatchedFiles} foto yang request_id nya tidak ada di database.");
        }

        if ($isDryRun) {
            $this->newLine();
            $this->info("✅ [DRY-RUN SELESAI]");
            $this->line("   Total Foto Valid     : {$matchedFiles}");
            $this->line("   Total Toko Terkait   : {$totalOutlets}");
            $this->line("   Foto Tidak Terhubung : {$unmatchedFiles}");
            return Command::SUCCESS;
        }

        // Eksekusi penyalinan file & pembaruan database
        $this->line("⏳ Menyalin file foto ke storage server & memperbarui database...");

        $bar = $this->output->createProgressBar($totalOutlets);
        $bar->start();

        $updatedOutlets = 0;

        foreach ($photosByRequest as $requestId => $types) {
            $updateData = [
                'photo_status' => 'COMPLETED',
                'updated_at' => now(),
            ];

            foreach ($types as $type => $info) {
                $destDir = dirname($info['absolute_target']);
                if (!is_dir($destDir)) {
                    File::makeDirectory($destDir, 0775, true, true);
                }

                // Salin file jika belum ada atau flag force aktif
                if ($isForce || !file_exists($info['absolute_target'])) {
                    @copy($info['source'], $info['absolute_target']);
                }

                if ($type === 'DEPAN') $updateData['photo_depan_path'] = $info['relative_target'];
                if ($type === 'DALAM') $updateData['photo_dalam_path'] = $info['relative_target'];
                if ($type === 'KTP')   $updateData['photo_ktp_path']   = $info['relative_target'];
            }

            // Update ke tabel noo_submissions
            DB::table('noo_submissions')->where('request_id', $requestId)->update($updateData);

            $updatedOutlets++;
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->info("🎉 MIGRASI FOTO SUKSES!");
        $this->table(
            ['Metrik', 'Jumlah'],
            [
                ['Total File Foto Diproses', $matchedFiles],
                ['Total Toko Diperbarui', $updatedOutlets],
                ['Foto Tidak Terkait di DB', $unmatchedFiles],
            ]
        );

        return Command::SUCCESS;
    }
}
