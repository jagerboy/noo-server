<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Enum status tahapan workflow registrasi outlet NOO+.
 */
enum NooStatusEnum: string
{
    // Status awal setelah Sales Executive melakukan submit lewat Android app
    case SE_SUBMITTED = 'SE_SUBMITTED';

    // Status setelah Admin Distributor menginput custcode_distributor & submit ke SPV
    case PUSHED_TO_SPV = 'PUSHED_TO_SPV';

    // Status jika Admin Distributor menolak pengajuan toko
    case ADMIN_REJECTED = 'ADMIN_REJECTED';

    // Status setelah SPV Area melakukan persetujuan & pengisian rute
    case APPROVED_SPV = 'APPROVED_SPV';

    // Status jika SPV Area menolak pengajuan toko
    case REJECTED_SPV = 'REJECTED_SPV';

    // Status setelah EDP Principal melakukan persetujuan & penerbitan Kode NOO Principal
    case APPROVED_EDP = 'APPROVED_EDP';

    // Status jika EDP Principal menolak/mengembalikan pengajuan toko
    case REJECTED_EDP = 'REJECTED_EDP';

    /**
     * Mendapatkan label deskriptif bahasa Indonesia untuk status workflow.
     */
    public function label(): string
    {
        return match ($this) {
            self::SE_SUBMITTED => 'Submisi SE Baru',
            self::PUSHED_TO_SPV => 'Diteruskan ke SPV',
            self::ADMIN_REJECTED => 'Ditolak Admin Distributor',
            self::APPROVED_SPV => 'Disetujui SPV Area',
            self::REJECTED_SPV => 'Ditolak SPV Area',
            self::APPROVED_EDP => 'Disetujui EDP (Ready)',
            self::REJECTED_EDP => 'Dikembalikan EDP',
        };
    }
}
