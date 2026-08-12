<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Exception;

/**
 * Service untuk menangani generasi kode outlet baru (code_noo_principal).
 * Penomoran bersifat otomatis dan terlindungi dari duplicate concurrency
 * menggunakan database transaction lock pada PostgreSQL.
 */
class CustomerCodeGeneratorService
{
    /**
     * Membuat kode outlet baru berdasarkan principal, branch, dan area code.
     *
     * @param string $principalCode Kode principal (misal: A)
     * @param string $branchId ID Cabang (misal: DATLK003)
     * @param string|null $areaCode Kode Area (misal: SUM1)
     * @return string Kode NOO Principal baru (contoh: CAMED00015)
     * @throws Exception
     */
    public function generateCode(string $principalCode, string $branchId, ?string $areaCode = null): string
    {
        $p = strtoupper(trim($principalCode));
        $bid = strtoupper(trim($branchId));

        if (empty($p)) {
            throw new Exception("Kode Principal tidak boleh kosong.");
        }

        if (empty($bid)) {
            throw new Exception("ID Cabang tidak boleh kosong.");
        }

        // Jalankan transaksi database dengan pessimistic locking (lockForUpdate)
        return DB::transaction(function () use ($p, $bid, $areaCode) {
            $counter = DB::table('counter_sequences')
                ->where('principal_code', $p)
                ->where('branch_id', $bid)
                ->lockForUpdate()
                ->first();

            if (!$counter) {
                throw new Exception("Counter Sequence belum terdaftar untuk branch_id: {$bid}");
            }

            $prefix = strtoupper(trim($counter->prefix));
            if (empty($prefix)) {
                throw new Exception("Prefix kode kosong di counter_sequences untuk branch: {$bid}");
            }

            $nextSeq = ((int) $counter->last_seq) + 1;

            // Perbarui nilai counter terakhir pada PostgreSQL
            DB::table('counter_sequences')
                ->where('id', $counter->id)
                ->update([
                    'last_seq' => $nextSeq,
                    'area_code' => $areaCode ?? $counter->area_code,
                    'last_updated_at' => now(),
                    'updated_at' => now(),
                ]);

            $seqStr = str_pad((string) $nextSeq, 5, '0', STR_PAD_LEFT);

            // Format penomoran utama NOO Principal: C + PrincipalCode + Prefix + 5-digit Sequence
            return "C{$p}{$prefix}{$seqStr}";
        });
    }
}
