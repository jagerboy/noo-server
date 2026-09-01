<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Exception;

/**
 * Service untuk memproses revisi foto KTP oleh EDP Principal.
 * Menambahkan watermark resmi: "FOTO KTP OWNER (REVISI)", info SE, Outlet, Branch, LA, LG, dan timestamp.
 */
class KtpRevisionService
{
    /**
     * Memproses file upload revisi foto KTP oleh EDP Principal.
     *
     * @param string $requestId UUID submisi toko
     * @param UploadedFile|string $fileOrContent File upload atau Base64
     * @param string $edpUsername Username EDP yang melakukan revisi
     * @return string Relative path file foto yang tersimpan
     * @throws Exception
     */
    public function processKtpRevision(string $requestId, $fileOrContent, string $edpUsername): string
    {
        $submission = DB::table('noo_submissions')->where('request_id', $requestId)->first();

        if (!$submission) {
            throw new Exception("Submisi NOO dengan request_id {$requestId} tidak ditemukan.");
        }

        // Dapatkan binary content gambar
        if ($fileOrContent instanceof UploadedFile) {
            $imageContent = file_get_contents($fileOrContent->getRealPath());
        } elseif (is_string($fileOrContent)) {
            $cleanBase64 = preg_replace('#^data:image/\w+;base64,#i', '', $fileOrContent);
            $imageContent = base64_decode($cleanBase64);
        } else {
            throw new Exception("Tipe file gambar tidak valid.");
        }

        if (!$imageContent) {
            throw new Exception("Gagal membaca berkas gambar KTP.");
        }

        // Terapkan watermark dengan PHP GD
        $watermarkedJpeg = $this->drawWatermark($imageContent, $submission);

        $nowText = now()->setTimezone('Asia/Jakarta')->format('Y-m-d H:i:s');
        $branchFolder = $submission->branch_id ?? 'BRANCH';
        $dateFolder = date('Y-m-d');
        $filename = "{$requestId}_ktp_revised_" . time() . ".jpg";
        $filePath = "noo_photos/{$branchFolder}/{$dateFolder}/{$filename}";

        // Simpan ke storage disk public
        Storage::disk('public')->put($filePath, $watermarkedJpeg);

        // Bersihkan token lama sebelum menambahkan flag revisi baru
        $flagsCleaned = preg_replace('/;?\s*(REVISI_KTP_EDP|UNLOCKED_KTP)[^;]*/i', '', (string) ($submission->flags ?? ''));
        $newFlags = trim(trim($flagsCleaned, '; ') . "; REVISI_KTP_EDP_BY_{$edpUsername}_AT_{$nowText}", '; ');

        // Update database dengan kolom header terdedikasi
        DB::table('noo_submissions')
            ->where('request_id', $requestId)
            ->update([
                'photo_ktp_path' => $filePath,
                'is_ktp_revised' => true,
                'ktp_revised_at' => now(),
                'ktp_revised_by' => $edpUsername,
                'flags' => $newFlags,
                'updated_at' => now(),
            ]);

        return $filePath;
    }

    /**
     * Menggambar watermark banner di bagian bawah gambar (extended canvas di bawah foto asli agar tidak menimpa foto).
     */
    private function drawWatermark(string $imageContent, object $submission): string
    {
        $src = @imagecreatefromstring($imageContent);
        if (!$src) {
            return $imageContent; // Fallback jika format tidak didukung GD
        }

        $origW = imagesx($src);
        $origH = imagesy($src);

        // Info SE
        $seName = $submission->nama_salesman ?? '';
        if (empty($seName) && !empty($submission->salesman_code)) {
            $seName = DB::table('master_salesmen')->where('salesman_code', $submission->salesman_code)->value('salesman_name') ?? $submission->salesman_code;
        }
        $seCode = $submission->salesman_code ?? '-';
        $seLine = $seName ? "SE: {$seName} ({$seCode})" : "SE: {$seCode}";
        $outletLine = "Outlet: " . ($submission->nama_noo ?? '-');
        $branchLine = "Branch: " . ($submission->branch_id ?? '-');
        $lat = $submission->latitude ?? '-';
        $lng = $submission->longitude ?? '-';
        $gpsLine = "LA: {$lat} | LG: {$lng}";
        $nowText = now()->setTimezone('Asia/Jakarta')->format('d-m-Y H:i');

        // Path font Arial / modern clean sans-serif
        $fontBold = resource_path('fonts/arialbd.ttf');
        if (!file_exists($fontBold)) $fontBold = 'C:/Windows/Fonts/arialbd.ttf';
        $fontRegular = resource_path('fonts/arial.ttf');
        if (!file_exists($fontRegular)) $fontRegular = 'C:/Windows/Fonts/arial.ttf';
        $hasTtf = file_exists($fontBold) && function_exists('imagettftext');

        // Tentukan tinggi banner tambahan di bawah foto (misal 20-22% dari tinggi, minimal 140px)
        $bannerHeight = max(140, intval($origH * 0.22));
        $totalHeight = $origH + $bannerHeight;

        // Buat canvas baru dengan total tinggi = Foto Asli + Banner Bawah
        $canvas = imagecreatetruecolor($origW, $totalHeight);

        // 1. Salin foto asli 1:1 di bagian atas (100% utuh tanpa terpotong / tertimpa)
        imagecopy($canvas, $src, 0, 0, 0, 0, $origW, $origH);
        imagedestroy($src);

        // 2. Buat background banner gelap (#111827 / Tailwind Slate 900)
        $bgColor = imagecolorallocate($canvas, 17, 24, 39); // #111827
        imagefilledrectangle($canvas, 0, $origH, $origW, $totalHeight, $bgColor);

        // Warna teks
        $whiteColor = imagecolorallocate($canvas, 255, 255, 255);
        $slateLight = imagecolorallocate($canvas, 226, 232, 240); // #E2E8F0
        $grayColor = imagecolorallocate($canvas, 148, 163, 184);  // #94A3B8

        if ($hasTtf) {
            // Skala ukuran font sesuai resolusi lebar gambar
            $scale = max(0.9, min(2.5, $origW / 640.0));
            $titleSize = intval(14 * $scale);
            $textSize = intval(11 * $scale);
            $lineSpacing = intval(18 * $scale);
            $paddingX = intval(16 * $scale);
            $startY = intval($origH + (24 * $scale));

            // Baris 1: Judul "FOTO KTP OWNER (REVISI)" (White/Bold)
            imagettftext($canvas, $titleSize, 0, $paddingX, $startY, $whiteColor, $fontBold, 'FOTO KTP OWNER (REVISI)');

            // Baris 2: SE
            imagettftext($canvas, $textSize, 0, $paddingX, intval($startY + ($lineSpacing * 1.15)), $slateLight, $fontRegular, substr($seLine, 0, 75));

            // Baris 3: Outlet
            imagettftext($canvas, $textSize, 0, $paddingX, intval($startY + ($lineSpacing * 2.15)), $slateLight, $fontRegular, substr($outletLine, 0, 75));

            // Baris 4: Branch
            imagettftext($canvas, $textSize, 0, $paddingX, intval($startY + ($lineSpacing * 3.15)), $slateLight, $fontRegular, substr($branchLine, 0, 75));

            // Baris 5: GPS Koordinat
            imagettftext($canvas, $textSize, 0, $paddingX, intval($startY + ($lineSpacing * 4.15)), $grayColor, $fontRegular, substr($gpsLine, 0, 75));

            // Baris 6: Timestamp
            imagettftext($canvas, $textSize, 0, $paddingX, intval($startY + ($lineSpacing * 5.15)), $grayColor, $fontRegular, $nowText);
        } else {
            // Fallback GD bitmap font
            $pX = 14;
            $startY = $origH + 10;
            imagestring($canvas, 5, $pX, $startY, 'FOTO KTP OWNER (REVISI)', $whiteColor);
            imagestring($canvas, 5, $pX + 1, $startY, 'FOTO KTP OWNER (REVISI)', $whiteColor); // bold effect
            imagestring($canvas, 4, $pX, $startY + 22, substr($seLine, 0, 70), $slateLight);
            imagestring($canvas, 4, $pX, $startY + 42, substr($outletLine, 0, 70), $slateLight);
            imagestring($canvas, 4, $pX, $startY + 62, substr($branchLine, 0, 70), $slateLight);
            imagestring($canvas, 4, $pX, $startY + 82, substr($gpsLine, 0, 70), $grayColor);
            imagestring($canvas, 4, $pX, $startY + 102, $nowText, $grayColor);
        }

        ob_start();
        imagejpeg($canvas, null, 92);
        $result = ob_get_clean();
        imagedestroy($canvas);

        return $result !== false ? $result : $imageContent;
    }
}
