<?php

declare(strict_types=1);

use App\Http\Controllers\Api\MobileApiController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Endpoint otentikasi default Sanctum
Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Grouping Endpoint REST API v1 khusus untuk Aplikasi Mobile Android NOO+ v2.0
Route::prefix('v1')->group(function () {
    // Tes koneksi server
    Route::get('/echo', [MobileApiController::class, 'echo']);
    Route::post('/echo', [MobileApiController::class, 'echo']);

    // Endpoint data master tipe outlet & branches
    Route::get('/master/branches', [MobileApiController::class, 'getMasterBranches']);
    Route::get('/master/outlet-types', [MobileApiController::class, 'getOutletTypes']);

    // Endpoint submisi toko dari aplikasi mobile Android
    Route::post('/noo/submit-meta', [MobileApiController::class, 'submitMeta']);
    Route::post('/noo/upload-photo', [MobileApiController::class, 'uploadPhoto']);
});

