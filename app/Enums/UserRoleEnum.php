<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Enum peranan/hak akses pengguna pada sistem ekosistem NOO+ v2.0.
 */
enum UserRoleEnum: string
{
    case SUPER_ADMIN = 'SUPER_ADMIN';
    case ADMIN_IT = 'ADMIN_IT';
    case ADMIN_DISTRIBUTOR = 'ADMIN_DISTRIBUTOR';
    case SPV = 'SPV';
    case EDP = 'EDP';

    /**
     * Mendapatkan label role pengguna untuk antarmuka web.
     */
    public function label(): string
    {
        return match ($this) {
            self::SUPER_ADMIN => 'Super Admin',
            self::ADMIN_IT => 'Admin IT Principal',
            self::ADMIN_DISTRIBUTOR => 'Admin Distributor',
            self::SPV => 'Supervisor Area (SPV)',
            self::EDP => 'EDP Principal',
        };
    }
}
