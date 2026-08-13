<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Enums\NooStatusEnum;
use App\Services\CustomerCodeGeneratorService;
use App\Services\ExcelExportService;
use App\Services\KtpRevisionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class EdpPortalController extends Controller
{
    public function __construct(
        protected CustomerCodeGeneratorService $codeGeneratorService,
        protected KtpRevisionService $ktpRevisionService,
        protected ExcelExportService $excelExportService
    ) {}

    private function logAction(string $action, string $module, string $description): void
    {
        $user = request()->user();
        DB::table('activity_logs')->insert([
            'username' => $user->name ?? $user->email ?? 'SYSTEM',
            'user_role' => $user->role ?? 'UNKNOWN',
            'action' => $action,
            'module' => $module,
            'description' => $description,
            'ip_address' => request()->ip(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function index(Request $request): Response
    {
        $user = $request->user();
        $regionCode = $user->region_code ?? null;
        $userRole = $user->role ?? 'EDP_REGION';

        $query = DB::table('noo_submissions')
            ->whereNotNull('pushed_to_edp_at');

        if ($userRole !== 'SUPERADMIN' && !empty($regionCode)) {
            $query->where('region_code', 'LIKE', "{$regionCode}%");
        }
        if ($userRole === 'ADMIN_PRINCIPAL' && !empty($user->entity_code_principal)) {
            $query->where(function ($q) use ($user) {
                $q->where('principal', 'ILIKE', "%{$user->entity_code_principal}%")
                  ->orWhere('principal_code', $user->entity_code_principal);
            });
        }

        if ($request->filled('region_code')) {
            $query->where('region_code', $request->input('region_code'));
        }
        if ($request->filled('principal')) {
            $p = $request->input('principal');
            $matchingBranchIds = DB::table('master_branches')
                ->where('entity_code_principal', $p)
                ->orWhere('principal_code', $p)
                ->pluck('branch_id')
                ->filter()
                ->toArray();

            $query->where(function ($q) use ($p, $matchingBranchIds) {
                $q->where('principal', 'ILIKE', "%{$p}%")
                  ->orWhere('principal_code', $p);
                if (!empty($matchingBranchIds)) {
                    $q->orWhereIn('branch_id', $matchingBranchIds);
                }
            });
        }
        if ($request->filled('branch_id')) {
            $b = $request->input('branch_id');
            $branchObj = DB::table('master_branches')->where('branch_id', $b)->first();
            $branchName = $branchObj ? $branchObj->branch_name : null;

            $query->where(function ($q) use ($b, $branchName) {
                $q->where('branch_id', $b);
                if (!empty($branchName)) {
                    $q->orWhere('branch_name', 'ILIKE', "%{$branchName}%");
                }
            });
        }
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }
        if ($request->filled('search')) {
            $s = $request->input('search');
            $query->where(function ($q) use ($s) {
                $q->where('nama_noo', 'ILIKE', "%{$s}%")
                  ->orWhere('custcode_distributor', 'ILIKE', "%{$s}%")
                  ->orWhere('code_noo_principal', 'ILIKE', "%{$s}%")
                  ->orWhere('salesman_code', 'ILIKE', "%{$s}%");
            });
        }

        if ($request->filled('edp_months')) {
            $rawMonths = explode(',', (string) $request->input('edp_months'));
            $monthsArray = array_values(array_filter(array_map('intval', $rawMonths)));
            if (!empty($monthsArray)) {
                $query->whereRaw("EXTRACT(MONTH FROM COALESCE(edp_reviewed_at, updated_at)) IN (" . implode(',', $monthsArray) . ")");
            }
        } elseif ($request->filled('edp_month')) {
            $m = (int) $request->input('edp_month');
            $query->whereRaw("EXTRACT(MONTH FROM COALESCE(edp_reviewed_at, updated_at)) = ?", [$m]);
        }

        if ($request->filled('edp_year')) {
            $y = (int) $request->input('edp_year');
            $query->whereRaw("EXTRACT(YEAR FROM COALESCE(edp_reviewed_at, updated_at)) = ?", [$y]);
        }

        $outletTypes = DB::table('master_outlet_types')->pluck('description', 'code')->toArray();

        $perPage = (int) $request->input('per_page', 10);
        if ($perPage <= 0) {
            $perPage = 100000;
        }

        $submissions = $query->orderBy('created_at', 'desc')->paginate($perPage)->withQueryString()->through(function ($item) use ($outletTypes) {
            $item->photo_depan_url = !empty($item->photo_depan_path)
                ? (str_starts_with($item->photo_depan_path, 'http') ? $item->photo_depan_path : asset('storage/' . $item->photo_depan_path))
                : null;
            $item->photo_dalam_url = !empty($item->photo_dalam_path)
                ? (str_starts_with($item->photo_dalam_path, 'http') ? $item->photo_dalam_path : asset('storage/' . $item->photo_dalam_path))
                : null;
            $updatedTs = strtotime((string)($item->updated_at ?? now()));
            $item->photo_ktp_url = !empty($item->photo_ktp_path)
                ? (str_starts_with($item->photo_ktp_path, 'http') ? $item->photo_ktp_path : asset('storage/' . $item->photo_ktp_path) . '?v=' . $updatedTs)
                : null;

            $item->is_ktp_revised = ($item->is_ktp_revised ?? false) || str_contains((string)($item->flags ?? ''), 'REVISI_KTP_EDP');
            $item->alamat_noo = \App\Services\NooSubmissionService::sanitizeAddress($item->alamat_noo ?? '');

            if (empty($item->type_outlet_desc) && !empty($item->type_outlet_code)) {
                $item->type_outlet_desc = $outletTypes[$item->type_outlet_code] ?? 'Retail';
            }

            return $item;
        });

        $regionsQuery = DB::table('master_branches')->select('region_code', 'region_name')->distinct()->whereNotNull('region_code');
        $entitiesQuery = DB::table('master_branches')->select('entity_code_principal', 'entity_name_principal', 'region_code')->distinct()->whereNotNull('entity_code_principal');
        $branchesQuery = DB::table('master_branches')->select('branch_id', 'branch_name', 'region_code', 'entity_code_principal');

        if ($userRole !== 'SUPERADMIN' && !empty($regionCode)) {
            $regionsQuery->where('region_code', 'LIKE', "{$regionCode}%");
            $entitiesQuery->where('region_code', 'LIKE', "{$regionCode}%");
            $branchesQuery->where('region_code', 'LIKE', "{$regionCode}%");
        }

        if ($userRole === 'ADMIN_PRINCIPAL' && !empty($user->entity_code_principal)) {
            $entitiesQuery->where('entity_code_principal', $user->entity_code_principal);
            $branchesQuery->where('entity_code_principal', $user->entity_code_principal);
        }

        $regions = $regionsQuery->orderBy('region_code')->get();
        $entities = $entitiesQuery->orderBy('entity_code_principal')->get();
        $branches = $branchesQuery->orderBy('branch_id', 'asc')->get();

        $edpYears = DB::table('noo_submissions')
            ->selectRaw("DISTINCT EXTRACT(YEAR FROM COALESCE(edp_reviewed_at, updated_at)) as yr")
            ->whereNotNull('edp_reviewed_at')
            ->pluck('yr')
            ->map(fn($y) => (int)$y)
            ->filter()
            ->sortDesc()
            ->values()
            ->toArray();
        if (empty($edpYears)) {
            $edpYears = [(int)date('Y')];
        }

        $activeFilters = array_filter(
            $request->only(['search', 'region_code', 'principal', 'branch_id', 'status', 'edp_month', 'edp_months', 'edp_year']),
            fn($val) => $val !== null && $val !== ''
        );

        return Inertia::render('Edp/Inbox', [
            'submissions' => $submissions,
            'userRegion' => $regionCode,
            'userRole' => $userRole,
            'filters' => $activeFilters,
            'filterOptions' => [
                'regions' => $regions,
                'entities' => $entities,
                'branches' => $branches,
                'edpYears' => $edpYears,
            ],
        ]);
    }

    public function updateStoreName(Request $request): RedirectResponse
    {
        $request->validate([
            'request_id' => 'required|uuid',
            'nama_noo' => 'required|string|max:200',
        ]);

        try {
            $requestId = $request->input('request_id');
            $newName = trim((string) $request->input('nama_noo'));
            $submission = DB::table('noo_submissions')->where('request_id', $requestId)->first();

            if (!$submission) {
                return back()->with('error', 'Data toko tidak ditemukan.');
            }

            $oldName = $submission->nama_noo;
            DB::table('noo_submissions')->where('request_id', $requestId)->update([
                'nama_noo' => $newName,
                'updated_at' => now(),
            ]);

            $this->logAction('UPDATE_STORE_NAME', 'NOO_VERIFICATION', "Mengubah nama toko ID {$requestId} dari '{$oldName}' menjadi '{$newName}'");

            return back()->with('success', "Nama toko berhasil diperbarui menjadi '{$newName}'");
        } catch (Throwable $e) {
            return back()->with('error', "Gagal memperbarui nama toko: {$e->getMessage()}");
        }
    }

    public function updateStoreAddress(Request $request): RedirectResponse
    {
        $request->validate([
            'request_id' => 'required|uuid',
            'alamat_noo' => 'required|string|max:500',
        ]);

        try {
            $requestId = $request->input('request_id');
            $newAddress = \App\Services\NooSubmissionService::sanitizeAddress($request->input('alamat_noo'));
            $submission = DB::table('noo_submissions')->where('request_id', $requestId)->first();

            if (!$submission) {
                return back()->with('error', 'Data toko tidak ditemukan.');
            }

            $oldAddress = $submission->alamat_noo;
            DB::table('noo_submissions')->where('request_id', $requestId)->update([
                'alamat_noo' => $newAddress,
                'updated_at' => now(),
            ]);

            $this->logAction('UPDATE_STORE_ADDRESS', 'NOO_VERIFICATION', "Mengubah alamat toko ID {$requestId} dari '{$oldAddress}' menjadi '{$newAddress}'");

            return back()->with('success', "Alamat toko berhasil diperbarui.");
        } catch (Throwable $e) {
            return back()->with('error', "Gagal memperbarui alamat toko: {$e->getMessage()}");
        }
    }

    public function approve(Request $request): RedirectResponse
    {
        $request->validate([
            'request_id' => 'required|uuid',
            'edp_notes' => 'nullable|string',
        ]);

        try {
            $requestId = $request->input('request_id');
            $submission = DB::table('noo_submissions')->where('request_id', $requestId)->first();

            if (!$submission) {
                return back()->with('error', 'Data toko tidak ditemukan.');
            }

            $user = $request->user();
            $userName = $user->name ?? $user->username ?? 'EDP Principal';

            $codeNoo = $submission->code_noo_principal;
            if (empty($codeNoo)) {
                $codeNoo = $this->codeGeneratorService->generateCode(
                    $submission->principal_code,
                    $submission->branch_id,
                    $submission->area_code
                );
            }

            DB::table('noo_submissions')->where('request_id', $requestId)->update([
                'code_noo_principal' => $codeNoo,
                'edp_notes' => $request->input('edp_notes'),
                'approved_by_edp' => $userName,
                'edp_decision' => 'APPROVED',
                'status' => NooStatusEnum::APPROVED_EDP->value,
                'is_ro' => true,
                'edp_reviewed_at' => now(),
                'updated_at' => now(),
            ]);

            $this->logAction('APPROVE_EDP', 'NOO_VERIFICATION', "Approved NOO {$submission->nama_noo} dengan Kode Principal: {$codeNoo}");

            return back()->with('success', "Toko {$submission->nama_noo} berhasil di-approve EDP dengan Kode Principal: {$codeNoo}");
        } catch (Throwable $e) {
            return back()->with('error', "Gagal approve EDP: {$e->getMessage()}");
        }
    }

    public function toggleRoStatus(Request $request): RedirectResponse
    {
        $request->validate([
            'request_id' => 'required|uuid',
            'is_ro' => 'required|boolean',
        ]);

        try {
            $requestId = $request->input('request_id');
            $isRo = (bool) $request->input('is_ro');

            $submission = DB::table('noo_submissions')->where('request_id', $requestId)->first();
            if (!$submission) {
                return back()->with('error', 'Data toko tidak ditemukan.');
            }

            DB::table('noo_submissions')->where('request_id', $requestId)->update([
                'is_ro' => $isRo,
                'updated_at' => now(),
            ]);

            $statusText = $isRo ? 'AKTIF (Registered Outlet)' : 'NON-AKTIF';
            $this->logAction('TOGGLE_RO_STATUS', 'NOO_VERIFICATION', "Mengubah status RO toko {$submission->nama_noo} menjadi {$statusText}");

            return back()->with('success', "Status RO toko {$submission->nama_noo} berhasil diubah menjadi {$statusText}");
        } catch (Throwable $e) {
            return back()->with('error', "Gagal mengubah status RO: {$e->getMessage()}");
        }
    }

    public function bulkToggleRoStatus(Request $request): RedirectResponse
    {
        $request->validate([
            'request_ids' => 'required|array',
            'request_ids.*' => 'required|uuid',
            'is_ro' => 'required|boolean',
        ]);

        try {
            $requestIds = $request->input('request_ids');
            $isRo = (bool) $request->input('is_ro');

            $count = DB::table('noo_submissions')
                ->whereIn('request_id', $requestIds)
                ->update([
                    'is_ro' => $isRo,
                    'updated_at' => now(),
                ]);

            $statusText = $isRo ? 'AKTIF (Registered Outlet)' : 'NON-AKTIF';
            $this->logAction('BULK_TOGGLE_RO_STATUS', 'NOO_VERIFICATION', "Mengubah status RO untuk {$count} toko menjadi {$statusText}");

            return back()->with('success', "Berhasil mengubah status RO secara massal untuk {$count} toko menjadi {$statusText}");
        } catch (Throwable $e) {
            return back()->with('error', "Gagal mengubah status RO massal: {$e->getMessage()}");
        }
    }

    public function reject(Request $request): RedirectResponse
    {
        $request->validate([
            'request_id' => 'required|uuid',
            'reject_reason' => 'nullable|string',
            'edp_notes' => 'nullable|string',
        ]);

        try {
            $requestId = $request->input('request_id');
            $submission = DB::table('noo_submissions')->where('request_id', $requestId)->first();

            if (!$submission) {
                return back()->with('error', 'Data toko tidak ditemukan.');
            }

            $user = $request->user();
            $userName = $user->name ?? $user->username ?? 'EDP Principal';
            $reason = trim((string)($request->input('reject_reason') ?? $request->input('edp_notes') ?? 'Ditolak EDP Principal'));

            DB::table('noo_submissions')->where('request_id', $requestId)->update([
                'edp_decision' => 'REJECTED',
                'reject_reason' => $reason,
                'approved_by_edp' => $userName,
                'status' => NooStatusEnum::REJECTED_EDP->value,
                'edp_reviewed_at' => now(),
                'updated_at' => now(),
            ]);

            $this->logAction('REJECT_EDP', 'NOO_VERIFICATION', "Rejected NOO {$submission->nama_noo}: {$reason}");

            return back()->with('success', "Toko {$submission->nama_noo} telah ditolak oleh EDP Principal.");
        } catch (Throwable $e) {
            return back()->with('error', "Gagal menolak toko: {$e->getMessage()}");
        }
    }

    public function cancelRejection(Request $request): RedirectResponse
    {
        $request->validate([
            'request_id' => 'required|uuid',
        ]);

        try {
            $userRole = strtoupper($request->user()->role ?? '');
            if (!in_array($userRole, ['SUPERADMIN', 'ADMIN_PRINCIPAL'])) {
                return back()->with('error', 'Hanya Superadmin dan Admin Principal yang diizinkan membatalkan penolakan.');
            }

            $requestId = $request->input('request_id');
            $submission = DB::table('noo_submissions')->where('request_id', $requestId)->first();

            if (!$submission || $submission->status !== NooStatusEnum::REJECTED_EDP->value) {
                return back()->with('error', 'Data toko tidak dalam status REJECTED_EDP.');
            }

            DB::table('noo_submissions')->where('request_id', $requestId)->update([
                'edp_decision' => null,
                'status' => NooStatusEnum::APPROVED_SPV->value,
                'updated_at' => now(),
            ]);

            $this->logAction('CANCEL_REJECT', 'NOO_VERIFICATION', "Membatalkan penolakan NOO {$submission->nama_noo}");

            return back()->with('success', "Penolakan toko {$submission->nama_noo} berhasil dibatalkan. Status kembali ke Verifikasi SPV.");
        } catch (Throwable $e) {
            return back()->with('error', "Gagal membatalkan penolakan: {$e->getMessage()}");
        }
    }

    public function resetEdpApproval(Request $request): RedirectResponse
    {
        $request->validate([
            'request_id' => 'required|uuid',
            'reason' => 'nullable|string',
        ]);

        try {
            $userRole = strtoupper($request->user()->role ?? '');
            if (!in_array($userRole, ['SUPERADMIN', 'ADMIN_PRINCIPAL'])) {
                return back()->with('error', 'Hanya Superadmin dan Admin Principal yang diizinkan melakukan Reset Approval EDP.');
            }

            $requestId = $request->input('request_id');
            $submission = DB::table('noo_submissions')->where('request_id', $requestId)->first();

            if (!$submission) {
                return back()->with('error', 'Data toko tidak ditemukan.');
            }

            $user = $request->user();
            $userName = $user->name ?? $user->username ?? 'SUPERADMIN';
            $oldCode = $submission->code_noo_principal ?? 'TANPA_KODE';
            $resetReason = trim((string)$request->input('reason'));
            if (empty($resetReason)) {
                $resetReason = "Reset Approval EDP oleh {$userName}";
            }

            DB::table('noo_submissions')->where('request_id', $requestId)->update([
                'status' => NooStatusEnum::APPROVED_SPV->value,
                'edp_decision' => null,
                'code_noo_principal' => null, // Reset Customer Code Principal
                'edp_reviewed_at' => null,
                'reset_reason' => $resetReason,
                'updated_at' => now(),
            ]);

            $this->logAction('RESET_EDP_APPROVAL', 'NOO_VERIFICATION', "Superadmin {$userName} mereset approval EDP & kode principal ({$oldCode}) toko {$submission->nama_noo}");

            return back()->with('success', "Approval EDP & Kode Customer Principal toko {$submission->nama_noo} berhasil di-reset kembali ke status Approved SPV.");
        } catch (Throwable $e) {
            return back()->with('error', "Gagal reset approval EDP: {$e->getMessage()}");
        }
    }

    public function reviseKtp(Request $request): RedirectResponse
    {
        $request->validate([
            'request_id' => 'required|uuid',
            'photo_ktp' => 'required|image|max:5120',
        ]);

        try {
            $requestId = $request->input('request_id');
            $submission = DB::table('noo_submissions')->where('request_id', $requestId)->first();

            if (!$submission) {
                return back()->with('error', 'Data toko tidak ditemukan.');
            }

            // Batasi revisi KTP maksimal 1 kali
            $isRevised = ($submission->is_ktp_revised ?? false) || str_contains((string)($submission->flags ?? ''), 'REVISI_KTP_EDP');
            if ($isRevised) {
                return back()->with('error', 'Foto KTP untuk toko ini sudah pernah direvisi (maksimal 1 kali).');
            }

            $file = $request->file('photo_ktp');
            $filename = "{$requestId}_ktp_revised_" . time() . '.' . $file->getClientOriginalExtension();
            $folder = "noo_photos/" . ($submission->branch_id ?? 'BRANCH') . "/" . date('Y-m-d');
            $path = $file->storeAs($folder, $filename, 'public');

            $nowText = now()->setTimezone('Asia/Jakarta')->format('Y-m-d H:i:s');
            $user = $request->user();
            $userName = $user->name ?? $user->username ?? 'EDP';

            DB::table('noo_submissions')->where('request_id', $requestId)->update([
                'photo_ktp_path' => $path,
                'flags' => trim(($submission->flags ?? '') . "; REVISI_KTP_EDP_BY_{$userName}_AT_{$nowText}"),
                'updated_at' => now(),
            ]);

            $this->logAction('REVISE_KTP', 'NOO_VERIFICATION', "Melakukan Revisi KTP 1x untuk toko {$submission->nama_noo}");

            return back()->with('success', "Foto KTP untuk toko {$submission->nama_noo} berhasil diperbarui.");
        } catch (Throwable $e) {
            return back()->with('error', "Gagal merevisi KTP: {$e->getMessage()}");
        }
    }

    public function resetKtpRevision(Request $request): RedirectResponse
    {
        $request->validate([
            'request_id' => 'required|uuid',
        ]);

        try {
            $userRole = strtoupper($request->user()->role ?? '');
            if (!in_array($userRole, ['SUPERADMIN', 'ADMIN_PRINCIPAL'])) {
                return back()->with('error', 'Hanya Superadmin dan Admin Principal yang diizinkan membuka kembali kunci revisi KTP.');
            }

            $requestId = $request->input('request_id');
            $submission = DB::table('noo_submissions')->where('request_id', $requestId)->first();

            if (!$submission) {
                return back()->with('error', 'Data toko tidak ditemukan.');
            }

            $nowText = now()->setTimezone('Asia/Jakarta')->format('Y-m-d H:i:s');
            $user = $request->user();
            $userName = $user->name ?? $user->username ?? 'SUPERADMIN';

            // Bersihkan flag REVISI_KTP_EDP agar status revisi ter-reset
            $flagsCleaned = preg_replace('/;?\s*REVISI_KTP_EDP[^\s;]*/i', '', $submission->flags ?? '');

            DB::table('noo_submissions')->where('request_id', $requestId)->update([
                'is_ktp_revised' => false,
                'flags' => trim($flagsCleaned . "; UNLOCKED_KTP_BY_{$userName}_AT_{$nowText}"),
                'updated_at' => now(),
            ]);

            $this->logAction('RESET_KTP_REVISION', 'NOO_VERIFICATION', "Superadmin {$userName} membuka kunci revisi KTP untuk toko {$submission->nama_noo}");

            return back()->with('success', "Akses revisi Foto KTP untuk toko {$submission->nama_noo} telah dibuka kembali.");
        } catch (Throwable $e) {
            return back()->with('error', "Gagal membuka kunci revisi KTP: {$e->getMessage()}");
        }
    }

    public function exportExcel(Request $request): StreamedResponse|RedirectResponse
    {
        $type = strtoupper($request->query('type', 'APPROVED'));
        $regionCode = $request->user()->region_code ?? null;
        $userRole = $request->user()->role ?? 'EDP_REGION';

        $query = DB::table('noo_submissions')->whereNotNull('pushed_to_edp_at');

        if ($userRole === 'EDP_REGION' && !empty($regionCode)) {
            $query->where('region_code', $regionCode);
        }

        if ($type === 'APPROVED') {
            $query->where(function ($q) {
                $q->where('edp_decision', 'APPROVED')
                  ->orWhereIn('status', [NooStatusEnum::APPROVED_EDP->value, 'APPROVED', 'INJECTED']);
            });
        } else {
            $query->where('status', NooStatusEnum::REJECTED_EDP->value);
        }

        $submissions = $query->orderBy('created_at', 'desc')->get()->map(fn($item) => (array) $item)->toArray();

        if (empty($submissions)) {
            return back()->with('error', "Tidak ada data NOO {$type} untuk diekspor.");
        }

        $branchName = $submissions[0]['branch_name'] ?? $submissions[0]['branch_id'] ?? 'ALL';
        $excelBinary = $this->excelExportService->generateExcel($submissions, $type, $branchName);

        $filename = "NOO_{$type}_" . date('Ymd_His') . ".xlsx";

        return response()->streamDownload(
            fn() => print($excelBinary),
            $filename,
            [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Cache-Control' => 'max-age=0',
            ]
        );
    }

    public function getApprovedExportData(Request $request): JsonResponse
    {
        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');
        $branchId = $request->query('branch_id');
        $userRegion = $request->user()->region_code ?? null;
        $userRole = $request->user()->role ?? 'EDP_REGION';

        $query = DB::table('noo_submissions')
            ->where(function ($q) {
                $q->where('edp_decision', 'APPROVED')
                  ->orWhereIn('status', [NooStatusEnum::APPROVED_EDP->value, 'APPROVED', 'INJECTED']);
            });

        if ($userRole === 'EDP_REGION' && !empty($userRegion)) {
            $query->where('region_code', $userRegion);
        }

        if (!empty($startDate)) {
            $query->whereRaw("DATE(COALESCE(edp_reviewed_at, updated_at, created_at)) >= ?", [$startDate]);
        }
        if (!empty($endDate)) {
            $query->whereRaw("DATE(COALESCE(edp_reviewed_at, updated_at, created_at)) <= ?", [$endDate]);
        }

        // Available branches ONLY containing branches with >0 approved submissions in this date range
        $branchesQuery = clone $query;
        $availableBranchIds = $branchesQuery
            ->whereNotNull('branch_id')
            ->where('branch_id', '!=', '')
            ->pluck('branch_id')
            ->unique()
            ->filter()
            ->values()
            ->toArray();

        $availableBranches = [];
        if (!empty($availableBranchIds)) {
            $availableBranches = DB::table('master_branches')
                ->whereIn('branch_id', $availableBranchIds)
                ->orderBy('region_code', 'asc')
                ->orderBy('branch_id', 'asc')
                ->get()
                ->map(function ($item) {
                    return [
                        'region_code' => $item->region_code ?? 'ALL',
                        'region_name' => $item->region_name ?? $item->region_code ?? 'REGIONAL',
                        'branch_id' => $item->branch_id,
                        'branch_name' => $item->branch_name ?? $item->branch_id,
                    ];
                });
        }

        if (!empty($branchId)) {
            $query->where(function ($q) use ($branchId) {
                $q->where('branch_id', $branchId)
                  ->orWhere('branch_name', 'ILIKE', "%{$branchId}%");
            });
        }

        $submissions = $query->orderBy('created_at', 'desc')->get()->map(function ($item) {
            return [
                'request_id' => $item->request_id,
                'branch_id' => $item->branch_id,
                'branch_name' => $item->branch_name ?? $item->branch_id,
                'code_noo_principal' => $item->code_noo_principal,
                'custcode_distributor' => $item->custcode_distributor,
                'nama_noo' => $item->nama_noo,
                'edp_decision' => $item->edp_decision ?? 'APPROVED',
                'submitted_at' => $item->submitted_at ?? $item->created_at,
                'edp_reviewed_at' => $item->edp_reviewed_at,
            ];
        });

        return response()->json([
            'branches' => $availableBranches,
            'submissions' => $submissions,
        ]);
    }

    public function exportApprovedSelected(Request $request): StreamedResponse|RedirectResponse
    {
        $requestIds = $request->input('request_ids', []);
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        $branchId = $request->input('branch_id');
        $userRegion = $request->user()->region_code ?? null;
        $userRole = $request->user()->role ?? 'EDP_REGION';

        $query = DB::table('noo_submissions')
            ->where(function ($q) {
                $q->where('edp_decision', 'APPROVED')
                  ->orWhereIn('status', [NooStatusEnum::APPROVED_EDP->value, 'APPROVED', 'INJECTED']);
            });

        if ($userRole === 'EDP_REGION' && !empty($userRegion)) {
            $query->where('region_code', $userRegion);
        }

        if (is_array($requestIds) && count($requestIds) > 0) {
            $query->whereIn('request_id', $requestIds);
        } else {
            if (!empty($startDate)) {
                $query->whereRaw("COALESCE(edp_reviewed_at, updated_at, created_at)::date >= ?", [$startDate]);
            }
            if (!empty($endDate)) {
                $query->whereRaw("COALESCE(edp_reviewed_at, updated_at, created_at)::date <= ?", [$endDate]);
            }
            if (!empty($branchId)) {
                $query->where('branch_id', $branchId);
            }
        }

        $submissions = $query->orderBy('created_at', 'desc')->get()->map(fn($item) => (array) $item)->toArray();

        if (empty($submissions)) {
            return back()->with('error', 'Tidak ada data NOO Approved yang dipilih untuk diekspor.');
        }

        $branchName = $submissions[0]['branch_name'] ?? $submissions[0]['branch_id'] ?? 'ALL';
        $excelBinary = $this->excelExportService->generateExcel($submissions, 'APPROVED', $branchName);

        $filename = "EXPORT_TEMPLATE_NOO_" . ($submissions[0]['branch_id'] ?? 'APPROVED') . "_" . date('Ymd_His') . ".xlsx";

        return response()->streamDownload(
            fn() => print($excelBinary),
            $filename,
            [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Cache-Control' => 'max-age=0',
            ]
        );
    }

    public function getRejectedExportData(Request $request): JsonResponse
    {
        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');
        $branchId = $request->query('branch_id');
        $userRegion = $request->user()->region_code ?? null;
        $userRole = $request->user()->role ?? 'EDP_REGION';

        $query = DB::table('noo_submissions')
            ->where(function ($q) {
                $q->where('edp_decision', 'REJECTED')
                  ->orWhere('status', NooStatusEnum::REJECTED_EDP->value);
            });

        if ($userRole === 'EDP_REGION' && !empty($userRegion)) {
            $query->where('region_code', $userRegion);
        }

        if (!empty($startDate)) {
            $query->whereRaw("COALESCE(edp_reviewed_at, updated_at, created_at)::date >= ?", [$startDate]);
        }
        if (!empty($endDate)) {
            $query->whereRaw("COALESCE(edp_reviewed_at, updated_at, created_at)::date <= ?", [$endDate]);
        }

        $branchesQuery = clone $query;
        $availableBranches = $branchesQuery
            ->select('region_code', 'branch_id', 'branch_name')
            ->whereNotNull('branch_id')
            ->distinct()
            ->orderBy('region_code', 'asc')
            ->orderBy('branch_id', 'asc')
            ->get()
            ->map(function ($item) {
                $regionName = DB::table('master_branches')
                    ->where('region_code', $item->region_code)
                    ->value('region_name');
                return [
                    'region_code' => $item->region_code ?? 'ALL',
                    'region_name' => $regionName ?? $item->region_code ?? 'REGIONAL',
                    'branch_id' => $item->branch_id,
                    'branch_name' => $item->branch_name ?? $item->branch_id,
                ];
            });

        if (!empty($branchId)) {
            $query->where('branch_id', $branchId);
        }

        $submissions = $query->orderBy('created_at', 'desc')->get()->map(function ($item) {
            return [
                'request_id' => $item->request_id,
                'branch_id' => $item->branch_id,
                'branch_name' => $item->branch_name ?? $item->branch_id,
                'code_noo_principal' => $item->code_noo_principal,
                'custcode_distributor' => $item->custcode_distributor,
                'nama_noo' => $item->nama_noo,
                'edp_decision' => $item->edp_decision ?? 'REJECTED',
                'edp_notes' => $item->edp_notes ?? $item->reject_reason ?? '-',
                'submitted_at' => $item->submitted_at ?? $item->created_at,
                'edp_reviewed_at' => $item->edp_reviewed_at,
            ];
        });

        return response()->json([
            'branches' => $availableBranches,
            'submissions' => $submissions,
        ]);
    }

    public function exportRejectedSelected(Request $request): StreamedResponse|RedirectResponse
    {
        $requestIds = $request->input('request_ids', []);
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        $branchId = $request->input('branch_id');
        $userRegion = $request->user()->region_code ?? null;
        $userRole = $request->user()->role ?? 'EDP_REGION';

        $query = DB::table('noo_submissions')
            ->where(function ($q) {
                $q->where('edp_decision', 'REJECTED')
                  ->orWhere('status', NooStatusEnum::REJECTED_EDP->value);
            });

        if ($userRole === 'EDP_REGION' && !empty($userRegion)) {
            $query->where('region_code', $userRegion);
        }

        if (is_array($requestIds) && count($requestIds) > 0) {
            $query->whereIn('request_id', $requestIds);
        } else {
            if (!empty($startDate)) {
                $query->whereRaw("COALESCE(edp_reviewed_at, updated_at, created_at)::date >= ?", [$startDate]);
            }
            if (!empty($endDate)) {
                $query->whereRaw("COALESCE(edp_reviewed_at, updated_at, created_at)::date <= ?", [$endDate]);
            }
            if (!empty($branchId)) {
                $query->where('branch_id', $branchId);
            }
        }

        $submissions = $query->orderBy('created_at', 'desc')->get()->map(fn($item) => (array) $item)->toArray();

        if (empty($submissions)) {
            return back()->with('error', 'Tidak ada data NOO Rejected yang dipilih untuk diekspor.');
        }

        $branchName = $submissions[0]['branch_name'] ?? $submissions[0]['branch_id'] ?? 'ALL';
        $excelBinary = $this->excelExportService->generateRejectedExcel($submissions, $branchName);

        $filename = "EXPORT_REJECTED_NOO_" . ($submissions[0]['branch_id'] ?? 'REJECTED') . "_" . date('Ymd_His') . ".xlsx";

        return response()->streamDownload(
            fn() => print($excelBinary),
            $filename,
            [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Cache-Control' => 'max-age=0',
            ]
        );
    }
}
