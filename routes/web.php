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

Route::get('/', function () {
    return redirect()->route('edp_login.create');
});

// Rute Login Bertingkat khusus Admin Distributor
Route::get('/distributor-login', [DistributorLoginController::class, 'create'])->name('distributor_login.create');
Route::get('/distributor-login/bootstrap', [DistributorLoginController::class, 'getBootstrapData'])->name('distributor_login.bootstrap');
Route::post('/distributor-login', [DistributorLoginController::class, 'store'])->name('distributor_login.store');
Route::post('/distributor-logout', [DistributorLoginController::class, 'destroy'])->name('distributor_logout');

// Rute Login khusus Supervisor Area (master_spvs)
Route::get('/spv-login', [SpvLoginController::class, 'create'])->name('spv_login.create');
Route::post('/spv-login', [SpvLoginController::class, 'store'])->name('spv_login.store');
Route::post('/spv-logout', [SpvLoginController::class, 'destroy'])->name('spv_logout');

// Rute Login & Logout khusus NOO+ Principal Portal
Route::get('/principal-login', [EdpLoginController::class, 'create'])->name('edp_login.create');
Route::post('/principal-login', [EdpLoginController::class, 'store'])->name('edp_login.store');
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
    });
});

require __DIR__.'/auth.php';
