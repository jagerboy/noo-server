<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\NooStatusEnum;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Exception;

/**
 * Service utama penanganan alur registrasi & lifecycle outlet NOO+.
 * Mengelola pendaftaran dari mobile app, pengisian admin distributor,
 * persetujuan SPV Area, hingga keputusan EDP Principal.
 */
class NooSubmissionService
{
    /**
     * Membersihkan dan memformat nama toko/outlet.
     */
    public static function sanitizeOutletName(?string $name): string
    {
        if (empty($name)) {
            return '';
        }
        return trim((string) preg_replace('/\s+/', ' ', $name));
    }

    /**
     * Membersihkan Pluscode di awal alamat (misal: "GPQ9+FXQ, Timbang Deli..." -> "Timbang Deli...").
     */
    public static function sanitizeAddress(?string $address): string
    {
        if (empty($address)) {
            return '';
        }
        $cleaned = preg_replace('/^[A-Z0-9]{4,8}\+[A-Z0-9]{2,4}(?:\s*,\s*|\s+)/i', '', trim($address));
        return trim((string) $cleaned);
    }
    /**
     * Menyimpan metadata submisi toko baru dari aplikasi Android SE.
     *
     * @param array $data Payload metadata toko dari mobile app
     * @return array Respon konfirmasi pendaftaran
     * @throws Exception
     */
    public function storeMetaFromApp(array $data): array
    {
        $requestId = trim($data['request_id'] ?? '');

        if (empty($requestId)) {
            throw new Exception("Parameter request_id wajib diisi.");
        }

        // Cek idempotency: jika request_id sudah ada di DB PostgreSQL, return data existing
        $existing = DB::table('noo_submissions')->where('request_id', $requestId)->first();
        if ($existing) {
            return [
                'ok' => true,
                'committed' => true,
                'duplicated' => true,
                'request_id' => $requestId,
                'message' => 'Data utama toko sudah tersimpan di database terpusat.'
            ];
        }

        $branchId = trim($data['branch_id'] ?? '');
        $branch = DB::table('master_branches')
            ->whereRaw('LOWER(branch_id) = ?', [strtolower($branchId)])
            ->first();

        $pinInput = trim((string)($data['pin_branch'] ?? ''));
        if ($branch && !empty($branch->pin_branch)) {
            // Evaluasi PIN secara toleran (case-insensitive & trimmed)
            if (strtolower(trim((string)$branch->pin_branch)) !== strtolower($pinInput)) {
                throw new Exception("PIN Branch '{$pinInput}' tidak sesuai dengan data master.");
            }
        }

        $salesmanCode = trim($data['salesman_code'] ?? '');
        $salesman = DB::table('master_salesmen')
            ->whereRaw('LOWER(salesman_code) = ?', [strtolower($salesmanCode)])
            ->first();

        // Nama fallback jika cabang/salesman baru belum terdaftar di tabel master server
        $branchName = $branch->branch_name ?? $branchId;
        $regionCode = trim($data['region_code'] ?? ($branch->region_code ?? ''));
        $areaCode = $branch->area_code ?? '';
        $salesmanName = $salesman->salesman_name ?? $salesmanCode;

        // Menggunakan timestamp perangkat Android penginput (ts) jika tersedia, atau jam lokal Asia/Jakarta
        $tsMs = isset($data['ts']) ? (int) $data['ts'] : null;
        $submittedAt = $tsMs ? \Carbon\Carbon::createFromTimestampMs($tsMs)->setTimezone('Asia/Jakarta') : now();

        $typeOutletCode = trim($data['type_outlet_code'] ?? '');
        $typeOutletDesc = trim($data['type_outlet_desc'] ?? '');

        if (empty($typeOutletDesc) && !empty($typeOutletCode)) {
            $outletType = DB::table('master_outlet_types')->where('code', $typeOutletCode)->first();
            if ($outletType) {
                $typeOutletDesc = $outletType->description;
            }
        }

        $pemilikName = trim($data['nama_pemilik_outlet'] ?? $data['nama_pemilik'] ?? $data['pemilik_outlet'] ?? '');
        $phoneNoo = trim($data['no_hp_noo'] ?? $data['no_hp'] ?? $data['no_hp_pemilik'] ?? '');
        $alamatNoo = self::sanitizeAddress($data['alamat_noo'] ?? '');
        $subGroupRegion = trim($data['sub_group_region'] ?? $data['principal'] ?? '');

        DB::table('noo_submissions')->insert([
            'request_id' => $requestId,
            'submitted_at' => $submittedAt,
            'principal' => trim($data['principal'] ?? 'ASWFOODS'),
            'principal_code' => trim($data['principal_code'] ?? 'A'),
            'sub_group_region' => $subGroupRegion,
            'region_code' => $regionCode,
            'branch_id' => $branchId,
            'branch_name' => $branchName,
            'area_code' => $areaCode,
            'salesman_code' => $salesmanCode,
            'salesman_name' => $salesmanName,
            'nama_noo' => trim($data['nama_noo'] ?? ''),
            'nama_pemilik_outlet' => $pemilikName,
            'no_hp_noo' => $phoneNoo,
            'no_hp' => $phoneNoo,
            'alamat_noo' => $alamatNoo,
            'kel_noo' => trim($data['kel_noo'] ?? ''),
            'kec_noo' => trim($data['kec_noo'] ?? ''),
            'kab_kota_noo' => trim($data['kab_kota_noo'] ?? ''),
            'provinsi_noo' => trim($data['provinsi_noo'] ?? ''),
            'type_outlet_code' => $typeOutletCode,
            'type_outlet_desc' => $typeOutletDesc,
            'la' => (float) ($data['la'] ?? 0),
            'lg' => (float) ($data['lg'] ?? 0),
            'accuracy_m' => (float) ($data['accuracy_m'] ?? 0),
            'samples_count' => (int) ($data['samples_count'] ?? 10),
            'sampling_interval_sec' => (int) ($data['sampling_interval_sec'] ?? 1),
            'geo_duration_sec' => (int) ($data['geo_duration_sec'] ?? 30),
            'locked_la' => isset($data['locked_la']) ? (float)$data['locked_la'] : (float)($data['la'] ?? 0),
            'locked_lg' => isset($data['locked_lg']) ? (float)$data['locked_lg'] : (float)($data['lg'] ?? 0),
            'locked_accuracy_m' => isset($data['locked_accuracy_m']) ? (float)$data['locked_accuracy_m'] : (float)($data['accuracy_m'] ?? 0),
            'mock_flag_locked' => trim((string)($data['mock_flag_locked'] ?? '')),
            'submit_la' => isset($data['submit_la']) ? (float)$data['submit_la'] : null,
            'submit_lg' => isset($data['submit_lg']) ? (float)$data['submit_lg'] : null,
            'submit_accuracy_m' => isset($data['submit_accuracy_m']) ? (float)$data['submit_accuracy_m'] : null,
            'mock_flag_submit' => trim((string)($data['mock_flag_submit'] ?? '')),
            'submit_distance_m' => isset($data['submit_distance_m']) ? (float)$data['submit_distance_m'] : null,
            'submit_radius_m' => isset($data['submit_radius_m']) ? (float)$data['submit_radius_m'] : null,
            'photo_status' => 'PROGRESS',
            'status' => NooStatusEnum::SE_SUBMITTED->value,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [
            'ok' => true,
            'committed' => true,
            'duplicated' => false,
            'request_id' => $requestId,
            'branch_name' => $branchName,
            'message' => 'Data utama berhasil disimpan di database server. Silakan upload foto toko.'
        ];
    }

    /**
     * Menghitung jarak Haversine (dalam meter) antara dua titik koordinat GPS.
     */
    public static function calculateHaversineDistance(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadius = 6371000;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) * sin($dLat / 2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLon / 2) * sin($dLon / 2);
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        return round($earthRadius * $c, 2);
    }

    /**
     * Membaca metadata EXIF koordinat GPS dari berkas JPEG foto.
     */
    private function extractExifGps(string $filePath): ?array
    {
        try {
            $fullPath = storage_path('app/public/' . $filePath);
            if (!file_exists($fullPath) || !function_exists('exif_read_data')) {
                return null;
            }
            $exif = @exif_read_data($fullPath, 'EXIF', true);
            if (!$exif || !isset($exif['GPS'])) {
                return null;
            }
            $gps = $exif['GPS'];
            if (!isset($gps['GPSLatitude'], $gps['GPSLongitude'], $gps['GPSLatitudeRef'], $gps['GPSLongitudeRef'])) {
                return null;
            }
            $lat = $this->convertGpsComponent($gps['GPSLatitude'], (string)$gps['GPSLatitudeRef']);
            $lng = $this->convertGpsComponent($gps['GPSLongitude'], (string)$gps['GPSLongitudeRef']);
            return ['lat' => $lat, 'lng' => $lng];
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Konversi komponen derajat/menit/detik EXIF GPS menjadi desimal.
     */
    private function convertGpsComponent(array $coord, string $hemisphere): float
    {
        $degrees = count($coord) > 0 ? $this->evalGpsRational($coord[0]) : 0;
        $minutes = count($coord) > 1 ? $this->evalGpsRational($coord[1]) : 0;
        $seconds = count($coord) > 2 ? $this->evalGpsRational($coord[2]) : 0;
        $result = $degrees + ($minutes / 60) + ($seconds / 3600);
        return (strtoupper($hemisphere) === 'S' || strtoupper($hemisphere) === 'W') ? -$result : $result;
    }

    /**
     * Evaluasi rasional pecahan string EXIF.
     */
    private function evalGpsRational($rational): float
    {
        if (is_numeric($rational)) {
            return (float) $rational;
        }
        $parts = explode('/', (string) $rational);
        if (count($parts) === 2 && (float)$parts[1] > 0) {
            return (float)$parts[0] / (float)$parts[1];
        }
        return (float) $rational;
    }

    /**
     * Mengunggah berkas foto toko (DEPAN, DALAM, KTP) ke storage server lokal.
     *
     * @param string $requestId Request UUID submisi toko
     * @param string $photoType Jenis foto ('DEPAN', 'DALAM', 'KTP')
     * @param string $base64Content String foto dalam format Base64
     * @return array Status pengunggahan foto
     * @throws Exception
     */
    public function uploadPhotoFromApp(string $requestId, string $photoType, string $base64Content): array
    {
        $submission = DB::table('noo_submissions')->where('request_id', $requestId)->first();

        if (!$submission) {
            throw new Exception("Submisi toko dengan request_id {$requestId} tidak ditemukan.");
        }

        $photoTypeUpper = strtoupper(trim($photoType));
        if (!in_array($photoTypeUpper, ['DEPAN', 'DALAM', 'KTP'])) {
            throw new Exception("Tipe foto tidak valid: {$photoType}");
        }

        // Decode string base64 menjadi file binary
        $cleanBase64 = preg_replace('#^data:image/\w+;base64,#i', '', $base64Content);
        $imageBytes = base64_decode($cleanBase64);

        if (!$imageBytes) {
            throw new Exception("Gagal decode isi foto Base64.");
        }

        // Tentukan path penyimpanan lokal di server (disk public storage)
        $dateFolder = date('Y-m-d', strtotime((string) $submission->submitted_at));
        $filePath = "noo_photos/{$submission->branch_id}/{$dateFolder}/{$requestId}_{$photoTypeUpper}.jpg";

        Storage::disk('public')->put($filePath, $imageBytes);

        // Update path foto spesifik pada tabel PostgreSQL
        $updateData = ['updated_at' => now()];
        if ($photoTypeUpper === 'DEPAN') $updateData['photo_depan_path'] = $filePath;
        if ($photoTypeUpper === 'DALAM') $updateData['photo_dalam_path'] = $filePath;
        if ($photoTypeUpper === 'KTP')   $updateData['photo_ktp_path']   = $filePath;

        // Ektraksi & Verifikasi EXIF Geotagging
        if (in_array($photoTypeUpper, ['DEPAN', 'DALAM'])) {
            $exifGps = $this->extractExifGps($filePath);
            if ($exifGps && (float)$submission->la != 0.0 && (float)$submission->lg != 0.0) {
                $distM = self::calculateHaversineDistance((float)$submission->la, (float)$submission->lg, $exifGps['lat'], $exifGps['lng']);
                if ($photoTypeUpper === 'DEPAN') {
                    $updateData['exif_depan_la'] = $exifGps['lat'];
                    $updateData['exif_depan_lg'] = $exifGps['lng'];
                    $updateData['exif_depan_distance_m'] = $distM;
                } else {
                    $updateData['exif_dalam_la'] = $exifGps['lat'];
                    $updateData['exif_dalam_lg'] = $exifGps['lng'];
                    $updateData['exif_dalam_distance_m'] = $distM;
                }
            }
        }

        DB::table('noo_submissions')->where('request_id', $requestId)->update($updateData);

        // Ambil data terbaru untuk verifikasi kelengkapan foto & validasi EXIF distance threshold (<15m)
        $updated = DB::table('noo_submissions')->where('request_id', $requestId)->first();
        $uploadedCount = 0;
        if (!empty($updated->photo_depan_path)) $uploadedCount++;
        if (!empty($updated->photo_dalam_path)) $uploadedCount++;
        if (!empty($updated->photo_ktp_path))   $uploadedCount++;

        $depanDist = $updated->exif_depan_distance_m !== null ? (float)$updated->exif_depan_distance_m : 0.0;
        $dalamDist = $updated->exif_dalam_distance_m !== null ? (float)$updated->exif_dalam_distance_m : 0.0;
        $isExifValid = ($depanDist <= 15.0 && $dalamDist <= 15.0);

        $isReady = ($uploadedCount >= 3);
        DB::table('noo_submissions')->where('request_id', $requestId)->update([
            'photo_status' => $isReady ? 'READY' : "PROGRESS: {$uploadedCount}/3 Foto Terupload",
            'is_exif_valid' => $isExifValid,
        ]);

        return [
            'ok' => true,
            'committed' => true,
            'request_id' => $requestId,
            'photo_type' => $photoTypeUpper,
            'uploaded_count' => $uploadedCount,
            'required_count' => 3,
            'photo_ready' => $isReady,
            'is_exif_valid' => $isExifValid,
            'message' => "Foto {$photoTypeUpper} berhasil disimpan di server."
        ];
    }
}

