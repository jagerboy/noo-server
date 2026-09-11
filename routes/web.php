<?php

declare(strict_types=1);

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Web\AdminDistributorController;
use App\Http\Controllers\Web\SpvPortalController;
use App\Http\Controllers\Web\EdpPortalController;
use App\Http\Controllers\Web\EdpDashboardController;
use App\Http\Controllers\Web\EdpMasterController;
use App\Http\Controllers\Web\EdpProgressController;
use App\Http\Controllers\Web\EdpAccountManagementController;
use App\Http\Controllers\Web\EdpLogsController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

use App\Http\Controllers\Auth\DistributorLoginController;
use App\Http\Controllers\Auth\SpvLoginController;
use App\Http\Controllers\Auth\EdpLoginController;

Route::get('/logo-noo-plus.png', function () {
    $dest = public_path('images/logo-noo-plus.png');
    if (!file_exists($dest)) {
        $dest = public_path('logo-noo-plus.png');
    }
    if (file_exists($dest)) {
        return response()->file($dest, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'no-cache, must-revalidate',
        ]);
    }
    abort(404);
});

Route::get('/Photo-Pabrik-ASW-Foods-Revisi.jpg', function () {
    $dest = public_path('images/Photo-Pabrik-ASW-Foods-Revisi.jpg');
    if (!file_exists($dest)) {
        $dest = public_path('Photo-Pabrik-ASW-Foods-Revisi.jpg');
    }
    if (!file_exists($dest)) {
        $dest = base_path('Photo-Pabrik-ASW-Foods-Revisi.jpg');
    }
    if (file_exists($dest)) {
        return response()->file($dest, [
            'Content-Type' => 'image/jpeg',
            'Cache-Control' => 'public, max-age=604800, immutable',
        ]);
    }
    abort(404);
});

// Utility verification endpoint (Khusus Superadmin terotentikasi)
Route::get('/init-db-columns', function () {
    if (!Auth::check() || Auth::user()->role !== 'SUPERADMIN') {
        abort(403, 'Akses terbatas untuk Superadmin.');
    }
    try {
        \Illuminate\Support\Facades\DB::statement('ALTER TABLE noo_submissions ADD COLUMN IF NOT EXISTS is_ktp_revised boolean DEFAULT false');
        \Illuminate\Support\Facades\DB::statement('ALTER TABLE noo_submissions ADD COLUMN IF NOT EXISTS ktp_revised_at timestamp null');
        \Illuminate\Support\Facades\DB::statement('ALTER TABLE noo_submissions ADD COLUMN IF NOT EXISTS ktp_revised_by varchar(100) null');
        \Illuminate\Support\Facades\DB::statement('ALTER TABLE noo_submissions ADD COLUMN IF NOT EXISTS ktp_unlocked_at timestamp null');
        \Illuminate\Support\Facades\DB::statement('ALTER TABLE noo_submissions ADD COLUMN IF NOT EXISTS ktp_unlocked_by varchar(100) null');
        \Illuminate\Support\Facades\DB::statement('ALTER TABLE noo_submissions ALTER COLUMN flags TYPE text');
        
        $cols = \Illuminate\Support\Facades\DB::select("SELECT column_name, data_type FROM information_schema.columns WHERE table_name = 'noo_submissions' AND column_name IN ('is_ktp_revised', 'ktp_revised_at', 'ktp_revised_by', 'ktp_unlocked_at', 'ktp_unlocked_by', 'flags')");
        
        return response()->json([
            'status' => 'SUCCESS',
            'message' => '5 KTP columns created/verified in noo_submissions successfully!',
            'columns' => $cols
        ]);
    } catch (\Throwable $e) {
        return response()->json([
            'status' => 'ERROR',
            'error' => $e->getMessage()
        ], 500);
    }
})->middleware('auth');


Route::get('/debug-photos', function () {
    if (!Auth::check() || Auth::user()->role !== 'SUPERADMIN') {
        abort(404);
    }
    $dir = storage_path('app/public/noo_photos/DAPLG002/2026-03-06');
    if (!is_dir($dir)) {
        $parent = storage_path('app/public/noo_photos/DAPLG002');
        $subdirs = is_dir($parent) ? array_values(array_diff(scandir($parent), ['.', '..'])) : [];
        return response()->json([
            'error' => "Directory not found: $dir",
            'available_dates' => $subdirs
        ]);
    }
    return response()->json([
        'dir' => $dir,
        'files' => array_values(array_diff(scandir($dir), ['.', '..']))
    ]);
})->middleware('auth');

Route::get('/', function () {
    return redirect('/principal');
});

// Dynamic Photo Stream Server Route (Hardened against Local File Disclosure & Directory Traversal)
Route::get('/media-photo/{path}', function ($path) {
    $cleanPath = ltrim(urldecode($path), '/');
    if (str_starts_with($cleanPath, 'public/')) $cleanPath = substr($cleanPath, 7);
    if (str_starts_with($cleanPath, 'storage/')) $cleanPath = substr($cleanPath, 8);
    if (str_starts_with($cleanPath, 'media-photo/')) $cleanPath = substr($cleanPath, 12);

    // Proteksi Keamanan: Cegah serangan Directory Traversal, file tersembunyi (dotfiles), dan karakter terlarang
    if (str_contains($cleanPath, '..') || str_contains($cleanPath, '\\') || str_starts_with(basename($cleanPath), '.')) {
        abort(403, 'Akses tidak diizinkan.');
    }

    // Validasi ekstensi: Hanya berkas gambar yang sah yang diperbolehkan diakses
    $ext = strtolower(pathinfo($cleanPath, PATHINFO_EXTENSION));
    $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
    if (!in_array($ext, $allowedExtensions, true)) {
        abort(404, 'Format berkas tidak diizinkan.');
    }

    // Hanya cari di dalam direktori penyimpanan publik yang sah (base_path dihilangkan demi keamanan)
    $candidatePaths = [
        storage_path('app/public/' . $cleanPath),
        public_path('storage/' . $cleanPath),
        public_path('images/' . $cleanPath),
        public_path($cleanPath),
    ];

    $allowedRoots = array_filter([
        realpath(storage_path('app/public')),
        realpath(public_path()),
    ]);

    $fullPath = null;
    foreach ($candidatePaths as $candidate) {
        $real = realpath($candidate);
        if ($real && file_exists($real)) {
            foreach ($allowedRoots as $root) {
                if (str_starts_with($real, $root)) {
                    $fullPath = $real;
                    break 2;
                }
            }
        }
    }

    // Fallback pencarian ekstensi file case-insensitive (.jpg vs .JPG vs .jpeg)
    if (!$fullPath) {
        $info = pathinfo($cleanPath);
        $dirname = $info['dirname'] ?? '';
        $filename = $info['filename'] ?? '';

        $altExtensions = ['jpg', 'jpeg', 'png', 'webp', 'JPG', 'JPEG', 'PNG', 'WEBP'];
        foreach ($altExtensions as $altExt) {
            $altClean = ($dirname && $dirname !== '.' ? $dirname . '/' : '') . $filename . '.' . $altExt;
            foreach ([storage_path('app/public/' . $altClean), public_path('storage/' . $altClean)] as $candidate) {
                $real = realpath($candidate);
                if ($real && file_exists($real)) {
                    foreach ($allowedRoots as $root) {
                        if (str_starts_with($real, $root)) {
                            $fullPath = $real;
                            break 3;
                        }
                    }
                }
            }
        }
    }

    if (!$fullPath) {
        abort(404);
    }

    $mime = @mime_content_type($fullPath);
    if (!$mime || $mime === 'text/plain' || $mime === 'application/octet-stream') {
        $handle = @fopen($fullPath, 'rb');
        $bytes = $handle ? fread($handle, 4) : '';
        if ($handle) fclose($handle);

        if (str_starts_with($bytes, "\xFF\xD8\xFF")) {
            $mime = 'image/jpeg';
        } elseif (str_starts_with($bytes, "\x89PNG")) {
            $mime = 'image/png';
        } elseif (str_starts_with($bytes, "GIF")) {
            $mime = 'image/gif';
        } else {
            $mime = 'image/jpeg';
        }
    }

    return response()->file($fullPath, [
        'Content-Type' => $mime,
        'Cache-Control' => 'public, max-age=86400',
    ]);
})->where('path', '.*')->name('media.photo');

// Entrypoint & Redirection khusus berdasarkan Sesi Device User
Route::get('/admin-distributor', function () {
    if (session()->has('distributor_user')) {
        return redirect()->route('admin.inbox');
    }
    return redirect()->route('distributor_login.create');
});

Route::get('/spv', function () {
    if (session()->has('spv_user')) {
        return redirect()->route('spv.inbox');
    }
    return redirect()->route('spv_login.create');
});

Route::get('/principal', function () {
    if (Auth::check()) {
        return redirect()->route('edp.dashboard');
    }
    return redirect()->route('edp_login.create');
});

// Rute Login Bertingkat khusus Admin Distributor (dengan Rate Limiting Brute Force Protection)
Route::get('/distributor-login', [DistributorLoginController::class, 'create'])->name('distributor_login.create');
Route::get('/distributor-login/bootstrap', [DistributorLoginController::class, 'getBootstrapData'])->name('distributor_login.bootstrap');
Route::post('/distributor-login', [DistributorLoginController::class, 'store'])->middleware('throttle:10,1')->name('distributor_login.store');
Route::post('/distributor-logout', [DistributorLoginController::class, 'destroy'])->name('distributor_logout');

// Rute Login khusus Supervisor Area (master_spvs)
Route::get('/spv-login', [SpvLoginController::class, 'create'])->name('spv_login.create');
Route::post('/spv-login', [SpvLoginController::class, 'store'])->middleware('throttle:10,1')->name('spv_login.store');
Route::post('/spv-logout', [SpvLoginController::class, 'destroy'])->name('spv_logout');

// Rute Login & Logout khusus NOO+ Principal Portal
Route::get('/principal-login', [EdpLoginController::class, 'create'])->name('edp_login.create');
Route::post('/principal-login', [EdpLoginController::class, 'store'])->middleware('throttle:10,1')->name('edp_login.store');
Route::post('/principal-logout', [EdpLoginController::class, 'destroy'])->name('edp_logout');


Route::get('/dashboard', function () {
    return redirect()->route('edp.dashboard');
})->middleware(['auth'])->name('dashboard');

// 1. Rute Portal Admin Distributor (Domain Publik / Session-based)
$distributorDomain = env('DOMAIN_DISTRIBUTOR');
$adminGroup = Route::name('admin.')->middleware('distributor.auth');
if (!empty($distributorDomain)) {
    $adminGroup->domain($distributorDomain);
} else {
    $adminGroup->prefix('admin-distributor');
}
$adminGroup->group(function () {
    Route::get('/inbox', [AdminDistributorController::class, 'index'])->name('inbox');
    Route::post('/submit-spv', [AdminDistributorController::class, 'submitToSpv'])->name('submit_spv');
    Route::post('/reject', [AdminDistributorController::class, 'reject'])->name('reject');
    Route::post('/update-nama-outlet', [AdminDistributorController::class, 'updateNamaOutlet'])->name('update_nama_outlet');
});

// 2. Rute Portal SPV Area (Domain Internal Server / Session-based)
$spvDomain = env('DOMAIN_SPV');
$spvGroup = Route::name('spv.')->middleware('spv.auth');
if (!empty($spvDomain)) {
    $spvGroup->domain($spvDomain);
} else {
    $spvGroup->prefix('spv');
}
$spvGroup->group(function () {
    Route::get('/inbox', [SpvPortalController::class, 'index'])->name('inbox');
    Route::get('/progress-tracking-data', [SpvPortalController::class, 'progressTrackingData'])->name('progress_tracking_data');
    Route::post('/approve', [SpvPortalController::class, 'approve'])->name('approve');
    Route::post('/reject', [SpvPortalController::class, 'reject'])->name('reject');
});

// 3. Rute Portal Principal & Master Data (Domain Internal Server / Auth User)
Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    $edpDomain = env('DOMAIN_PRINCIPAL') ?? env('DOMAIN_EDP');
    $edpGroup = Route::name('edp.');
    if (!empty($edpDomain)) {
        $edpGroup->domain($edpDomain);
    } else {
        $edpGroup->prefix('principal');
    }
    $edpGroup->group(function () {
        // Home Dashboard
        Route::get('/dashboard', [EdpDashboardController::class, 'index'])->name('dashboard');
        Route::match(['get', 'post'], '/dashboard/export-chart/{chartType}', [EdpDashboardController::class, 'exportChartExcel'])->name('dashboard.export_chart');
        Route::match(['get', 'post'], '/dashboard/export-chart-pdf/{chartType}', [EdpDashboardController::class, 'exportChartPdf'])->name('dashboard.export_chart_pdf');

        // Monitoring Target RO vs Realisasi Approved Salesman
        Route::get('/monitoring-ro', [EdpDashboardController::class, 'monitoringRo'])->name('monitoring_ro');
        Route::post('/monitoring-ro/upload-target', [EdpDashboardController::class, 'uploadTargetRo'])->name('monitoring_ro.upload_target');
        Route::get('/monitoring-ro/download-template', [EdpDashboardController::class, 'downloadTargetRoTemplate'])->name('monitoring_ro.download_template');

        // Inbox NOO Verification
        Route::get('/inbox', [EdpPortalController::class, 'index'])->name('inbox');
        Route::post('/approve', [EdpPortalController::class, 'approve'])->name('approve');
        Route::post('/reject', [EdpPortalController::class, 'reject'])->name('reject');
        Route::post('/cancel-rejection', [EdpPortalController::class, 'cancelRejection'])->name('cancel_rejection');
        Route::post('/reset-edp-approval', [EdpPortalController::class, 'resetEdpApproval'])->name('reset_edp_approval');
        Route::post('/revise-ktp', [EdpPortalController::class, 'reviseKtp'])->name('revise_ktp');
        Route::post('/reset-ktp-revision', [EdpPortalController::class, 'resetKtpRevision'])->name('reset_ktp_revision');
        Route::post('/update-store-name', [EdpPortalController::class, 'updateStoreName'])->name('update_store_name');
        Route::post('/update-store-address', [EdpPortalController::class, 'updateStoreAddress'])->name('update_store_address');
        Route::post('/toggle-ro-status', [EdpPortalController::class, 'toggleRoStatus'])->name('toggle_ro_status');
        Route::post('/bulk-toggle-ro-status', [EdpPortalController::class, 'bulkToggleRoStatus'])->name('bulk_toggle_ro_status');
        Route::get('/export-excel', [EdpPortalController::class, 'exportExcel'])->name('export_excel');
        Route::get('/export-approved-data', [EdpPortalController::class, 'getApprovedExportData'])->name('export_approved_data');
        Route::post('/export-approved-selected', [EdpPortalController::class, 'exportApprovedSelected'])->name('export_approved_selected');
        Route::get('/export-rejected-data', [EdpPortalController::class, 'getRejectedExportData'])->name('export_rejected_data');
        Route::post('/export-rejected-selected', [EdpPortalController::class, 'exportRejectedSelected'])->name('export_rejected_selected');

        // Monitoring Progress Submisi & Reset Inputan Admin / SPV
        Route::get('/progress-tracking', [EdpProgressController::class, 'index'])->name('progress_tracking');
        Route::post('/reset-admin-input', [EdpProgressController::class, 'resetAdminInput'])->name('reset_admin_input');
        Route::post('/reset-spv-input', [EdpProgressController::class, 'resetSpvInput'])->name('reset_spv_input');

        // Master Branch CRUD
        Route::get('/master-branch', [EdpMasterController::class, 'masterBranch'])->name('master_branch');
        Route::post('/master-branch', [EdpMasterController::class, 'storeBranch'])->name('master_branch.store');
        Route::put('/master-branch/{id}', [EdpMasterController::class, 'updateBranch'])->name('master_branch.update');
        Route::delete('/master-branch/{id}', [EdpMasterController::class, 'destroyBranch'])->name('master_branch.destroy');

        // Master Salesman CRUD
        Route::get('/master-salesman', [EdpMasterController::class, 'masterSalesman'])->name('master_salesman');
        Route::post('/master-salesman', [EdpMasterController::class, 'storeSalesman'])->name('master_salesman.store');
        Route::put('/master-salesman/{id}', [EdpMasterController::class, 'updateSalesman'])->name('master_salesman.update');
        Route::delete('/master-salesman/{id}', [EdpMasterController::class, 'destroySalesman'])->name('master_salesman.destroy');

        // Master SPV CRUD
        Route::get('/master-spv', [EdpMasterController::class, 'masterSpv'])->name('master_spv');
        Route::post('/master-spv', [EdpMasterController::class, 'storeSpv'])->name('master_spv.store');
        Route::put('/master-spv/{id}', [EdpMasterController::class, 'updateSpv'])->name('master_spv.update');
        Route::delete('/master-spv/{id}', [EdpMasterController::class, 'destroySpv'])->name('master_spv.destroy');

        // Master Outlet Types CRUD
        Route::get('/master-outlet-types', [EdpMasterController::class, 'masterOutletTypes'])->name('master_outlet_types');
        Route::post('/master-outlet-types', [EdpMasterController::class, 'storeOutletType'])->name('master_outlet_types.store');
        Route::put('/master-outlet-types/{id}', [EdpMasterController::class, 'updateOutletType'])->name('master_outlet_types.update');
        Route::delete('/master-outlet-types/{id}', [EdpMasterController::class, 'destroyOutletType'])->name('master_outlet_types.destroy');

        // Counter Sequence
        Route::get('/counter-sequence', [EdpMasterController::class, 'counterSequence'])->name('counter_sequence');
        Route::post('/counter-sequence', [EdpMasterController::class, 'storeCounterSequence'])->name('counter_sequence.store');
        Route::post('/counter-sequence/{id}', [EdpMasterController::class, 'updateCounterSequence'])->name('counter_sequence.update');

        // Bulk Upload & Download Template Master Data (Khusus SUPERADMIN)
        Route::get('/master-bulk-template/{type}', [EdpMasterController::class, 'downloadTemplate'])->name('master.download_template');
        Route::post('/master-bulk-upload/{type}', [EdpMasterController::class, 'bulkUpload'])->name('master.bulk_upload');

        // Manajemen Akun
        Route::get('/account-management', [EdpAccountManagementController::class, 'index'])->name('account_management');
        Route::post('/account-management', [EdpAccountManagementController::class, 'store'])->name('account_management.store');
        Route::put('/account-management/{id}', [EdpAccountManagementController::class, 'update'])->name('account_management.update');
        Route::delete('/account-management/{id}', [EdpAccountManagementController::class, 'destroy'])->name('account_management.destroy');

        // Audit Logs
        Route::get('/logs', [EdpLogsController::class, 'index'])->name('logs');
        Route::get('/logs/export-excel', [EdpLogsController::class, 'exportExcel'])->name('logs.export_excel');
    });
});

require __DIR__.'/auth.php';
