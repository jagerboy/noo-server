<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\NooSubmissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Controller API terpusat untuk melayani aplikasi mobile Android NOO+ v2.0.
 * Menggantikan seluruh endpoint Google Apps Script (NOO_API doGet / doPost).
 */
class MobileApiController extends Controller
{
    /**
     * Inisialisasi controller dengan menginjeksi service submisi toko.
     */
    public function __construct(
        protected NooSubmissionService $submissionService
    ) {}

    /**
     * Endpoint tes koneksi & status server untuk aplikasi mobile.
     *
     * @return JsonResponse Informasi status server & waktu WIB
     */
    public function echo(): JsonResponse
    {
        return response()->json([
            'ok' => true,
            'route' => 'echo',
            'version' => '2.0-Laravel',
            'time_wib' => now()->setTimezone('Asia/Jakarta')->format('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Endpoint mengambil master jenis outlet aktif.
     *
     * @return JsonResponse Daftar tipe outlet
     */
    public function getOutletTypes(): JsonResponse
    {
        try {
            $types = DB::table('master_outlet_types')
                ->where('is_active', true)
                ->select('code', 'description as desc')
                ->get();

            return response()->json([
                'ok' => true,
                'data' => $types
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'ok' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Endpoint mengambil master data cabang/branches lengkap dengan salesman & PIN.
     *
     * @return JsonResponse Data master cabang untuk aplikasi Android
     */
    public function getMasterBranches(): JsonResponse
    {
        try {
            $branches = DB::table('master_branches')
                ->where('is_active', true)
                ->get()
                ->map(function ($b) {
                    $b->active = 'TRUE';
                    return $b;
                });

            $salesmen = DB::table('master_salesmen')
                ->where('is_active', true)
                ->get()
                ->map(function ($s) {
                    $s->active = 'TRUE';
                    return $s;
                });

            $outletTypes = DB::table('master_outlet_types')
                ->where('is_active', true)
                ->get()
                ->map(function ($t) {
                    return [
                        'type_code' => $t->code,
                        'type_desc' => $t->description,
                        'active' => $t->is_active ? 'TRUE' : 'FALSE'
                    ];
                });

            return response()->json([
                'ok' => true,
                'rules' => [
                    'accuracy_threshold_m' => 50,
                    'samples_count' => 10,
                    'sampling_interval_sec' => 1
                ],
                'branches' => $branches,
                'salesmen' => $salesmen,
                'outlet_types' => $outletTypes,
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'ok' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Endpoint submit metadata toko dari aplikasi Android.
     *
     * @param Request $request Payload request submisi toko
     * @return JsonResponse Status simpan metadata
     */
    public function submitMeta(Request $request): JsonResponse
    {
        try {
            $result = $this->submissionService->storeMetaFromApp($request->all());
            $result['version'] = '2.0-Laravel';

            return response()->json($result);
        } catch (Throwable $e) {
            return response()->json([
                'ok' => false,
                'error' => $e->getMessage(),
                'version' => '2.0-Laravel'
            ], 400);
        }
    }

    /**
     * Endpoint unggah berkas foto dari aplikasi Android.
     *
     * @param Request $request Payload berisi base64 foto & request_id
     * @return JsonResponse Status pengunggahan foto
     */
    public function uploadPhoto(Request $request): JsonResponse
    {
        try {
            $requestId = (string) $request->input('request_id', '');
            $photoType = (string) $request->input('photo_type', '');
            $base64 = (string) $request->input('image_base64', '');

            $result = $this->submissionService->uploadPhotoFromApp($requestId, $photoType, $base64);
            $result['version'] = '2.0-Laravel';

            return response()->json($result);
        } catch (Throwable $e) {
            return response()->json([
                'ok' => false,
                'error' => $e->getMessage(),
                'version' => '2.0-Laravel'
            ], 400);
        }
    }
}
