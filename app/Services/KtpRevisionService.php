<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;
use Exception;

/**
 * Service untuk memproses revisi foto KTP oleh EDP Principal.
 * Mengganti foto KTP lama dan menambahkan watermark "FOTO KTP REVISI EDP"
 * beserta timestamp eksekusi tanpa menghilangkan informasi metadata sebelumnya.
 */
class KtpRevisionService
{
    /**
     * Memproses upload revisi foto KTP oleh EDP Principal.
     *
     * @param string $requestId Request UUID submisi toko
     * @param string $base64Image Content foto KTP baru (Base64)
     * @param string $edpUsername Username EDP yang melakukan revisi
     * @return string Relative path foto KTP revisi yang baru tersimpan
     * @throws Exception
     */
    public function reviseKtpPhoto(string $requestId, string $base64Image, string $edpUsername): string
    {
        $submission = DB::table('noo_submissions')->where('request_id', $requestId)->first();

        if (!$submission) {
            throw new Exception("Submisi NOO dengan request_id {$requestId} tidak ditemukan.");
        }

        // Decode Base64 foto KTP baru
        $cleanBase64 = preg_replace('#^data:image/\w+;base64,#i', '', $base64Image);
        $imageBytes = base64_decode($cleanBase64);

        if (!$imageBytes) {
            throw new Exception("Format gambar KTP Base64 tidak valid.");
        }

        $nowText = now()->setTimezone('Asia/Jakarta')->format('Y-m-d H:i:s');

        // Buat instance Intervention Image versi 3
        $manager = new ImageManager(new Driver());
        $image = $manager->read($imageBytes);

        // Tambahkan watermark teks "FOTO KTP REVISI EDP" dan timestamp di bagian bawah foto
        $watermarkText = "FOTO KTP REVISI EDP | By: {$edpUsername} | {$nowText} WIB";

        // Tulis watermark teks sederhana ke gambar KTP
        $image->text($watermarkText, 20, $image->height() - 30, function ($font) {
            $font->size(20);
            $font->color('#FF0000');
        });

        $revisedFilename = "{$requestId}_KTP_REVISI_EDP.jpg";
        $dateFolder = date('Y-m-d', strtotime((string) $submission->submitted_at));
        $filePath = "noo_photos/{$submission->branch_id}/{$dateFolder}/{$revisedFilename}";

        // Simpan foto revisi KTP ke public storage disk
        Storage::disk('public')->put($filePath, (string) $image->toJpeg());

        // Update database PostgreSQL dengan path KTP baru dan catat flag revisi
        DB::table('noo_submissions')
            ->where('request_id', $requestId)
            ->update([
                'photo_ktp_path' => $filePath,
                'flags' => trim(($submission->flags ?? '') . "; REVISI_KTP_EDP_BY_{$edpUsername}_AT_{$nowText}"),
                'updated_at' => now(),
            ]);

        return $filePath;
    }
}
