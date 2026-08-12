<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Enums\NooStatusEnum;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

/**
 * Controller Monitoring Progress Submisi NOO & Reset Inputan (Admin / SPV).
 * Mengizinkan Superadmin & Admin Principal melacak status perjalanan toko
 * dan membatalkan/mereset data yang sudah disubmit oleh Admin Distributor atau SPV Area.
 */
class EdpProgressController extends Controller
{
    private function logAction(string $action, string $module, string $description): void
    {
        $user = Auth::user();
        DB::table('activity_logs')->insert([
            'username' => $user->username ?? $user->name ?? $user->email,
            'user_role' => $user->role ?? 'UNKNOWN',
            'action' => $action,
            'module' => $module,
            'description' => $description,
            'ip_address' => request()->ip(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function getFilterOptions(?\App\Models\User $user = null): array
    {
        $user = $user ?? Auth::user();
        $userRole = $user->role ?? 'EDP_REGION';
        $regionCode = $user->region_code ?? null;

        $regionsQuery = DB::table('master_branches')
            ->select('region_code', 'region_name')
            ->distinct()
            ->whereNotNull('region_code');

        $entitiesQuery = DB::table('master_branches')
            ->select('entity_code_principal', 'entity_name_principal', 'region_code')
            ->distinct()
            ->whereNotNull('entity_code_principal');

        $branchesQuery = DB::table('master_branches')
            ->select('region_code', 'entity_code_principal', 'branch_id', 'branch_name')
            ->whereNotNull('branch_id');

        if ($userRole !== 'SUPERADMIN' && !empty($regionCode)) {
            $regionsQuery->where('region_code', 'LIKE', "{$regionCode}%");
            $entitiesQuery->where('region_code', 'LIKE', "{$regionCode}%");
            $branchesQuery->where('region_code', 'LIKE', "{$regionCode}%");
        }

        if ($userRole === 'ADMIN_PRINCIPAL' && !empty($user->entity_code_principal)) {
            $entitiesQuery->where('entity_code_principal', $user->entity_code_principal);
            $branchesQuery->where('entity_code_principal', $user->entity_code_principal);
        }

        return [
            'regions' => $regionsQuery->orderBy('region_code')->get(),
            'entities' => $entitiesQuery->orderBy('entity_code_principal')->get(),
            'branches' => $branchesQuery->orderBy('branch_id', 'asc')->get(),
        ];
    }

    /**
     * Menampilkan dashboard lacak progress submisi toko NOO+.
     */
    public function index(Request $request): Response
    {
        $user = Auth::user();
        $userRole = $user->role ?? 'EDP_REGION';

        $baseQuery = DB::table('noo_submissions');

        // Filter Scope berdasarkan Role Sesuai Spesifikasi Security
        if ($userRole !== 'SUPERADMIN' && !empty($user->region_code)) {
            $baseQuery->where('region_code', 'LIKE', "{$user->region_code}%");
        }

        if ($userRole === 'ADMIN_PRINCIPAL' && !empty($user->entity_code_principal)) {
            $matchingBranches = DB::table('master_branches')
                ->where('entity_code_principal', $user->entity_code_principal)
                ->pluck('branch_id')
                ->toArray();
            if (!empty($matchingBranches)) {
                $baseQuery->whereIn('branch_id', $matchingBranches);
            }
        }

        // Metrics global scope
        $allForMetrics = (clone $baseQuery)->get();
        $metrics = [
            'total' => $allForMetrics->count(),
            'stuckAdmin' => $allForMetrics->where('status', 'SE_SUBMITTED')->count(),
            'stuckSpv' => $allForMetrics->where('status', 'PUSHED_TO_SPV')->count(),
            'pendingEdp' => $allForMetrics->whereIn('status', ['APPROVED_SPV', 'PUSHED_TO_EDP'])->count(),
            'completed' => $allForMetrics->where('status', 'APPROVED_EDP')->count(),
            'rejected' => $allForMetrics->whereIn('status', [
                'ADMIN_REJECTED',
                'SPV_REJECTED',
                'REJECTED_SPV',
                'EDP_REJECTED',
                'REJECTED_EDP',
            ])->count(),
        ];

        // Filter Interaktif
        $query = clone $baseQuery;

        if ($request->filled('stage')) {
            $stage = $request->input('stage');
            if ($stage === 'stuck_admin') {
                $query->where('status', 'SE_SUBMITTED');
            } elseif ($stage === 'stuck_spv') {
                $query->where('status', 'PUSHED_TO_SPV');
            } elseif ($stage === 'pending_edp') {
                $query->whereIn('status', ['APPROVED_SPV', 'PUSHED_TO_EDP']);
            } elseif ($stage === 'completed') {
                $query->where('status', 'APPROVED_EDP');
            } elseif ($stage === 'rejected') {
                $query->whereIn('status', [
                    'ADMIN_REJECTED',
                    'SPV_REJECTED',
                    'REJECTED_SPV',
                    'EDP_REJECTED',
                    'REJECTED_EDP',
                ]);
            }
        }

        if ($request->filled('region_code')) {
            $query->where('region_code', $request->input('region_code'));
        }
        if ($request->filled('branch_id')) {
            $query->where('branch_id', $request->input('branch_id'));
        }
        if ($request->filled('search')) {
            $s = $request->input('search');
            $query->where(function ($q) use ($s) {
                $q->where('nama_noo', 'ILIKE', "%{$s}%")
                  ->orWhere('salesman_name', 'ILIKE', "%{$s}%")
                  ->orWhere('custcode_distributor', 'ILIKE', "%{$s}%")
                  ->orWhere('code_noo_principal', 'ILIKE', "%{$s}%")
                  ->orWhere('branch_id', 'ILIKE', "%{$s}%");
            });
        }

        $perPage = (int) $request->input('per_page', 15);
        if ($perPage <= 0) $perPage = 15;

        $submissions = $query->orderBy('created_at', 'desc')->paginate($perPage)->withQueryString();

        $submissions->getCollection()->transform(function ($item) {
            $item->photo_depan_url = $item->photo_depan_path ? asset('storage/' . $item->photo_depan_path) : null;
            $item->photo_dalam_url = $item->photo_dalam_path ? asset('storage/' . $item->photo_dalam_path) : null;
            $item->photo_ktp_url = $item->photo_ktp_path ? asset('storage/' . $item->photo_ktp_path) : null;

            // Kategori Tahapan Progress Workflow
            if ($item->status === 'SE_SUBMITTED') {
                $item->stage_label = 'Admin belum memproses NOO dengan mengisikan kode customer versi distributor';
                $item->stage_code = 'STUCK_ADMIN';
            } elseif ($item->status === 'PUSHED_TO_SPV') {
                $item->stage_label = 'SPV belum memproses NOO dengan mengisikan JKS';
                $item->stage_code = 'STUCK_SPV';
            } elseif (in_array($item->status, ['APPROVED_SPV', 'PUSHED_TO_EDP'])) {
                $item->stage_label = 'Pending Verifikasi EDP';
                $item->stage_code = 'PENDING_EDP';
            } elseif ($item->status === 'APPROVED_EDP') {
                $item->stage_label = 'Selesai / Approved EDP';
                $item->stage_code = 'COMPLETED';
            } else {
                $item->stage_label = 'Ditolak / Rejected';
                $item->stage_code = 'REJECTED';
            }

            return $item;
        });

        return Inertia::render('Edp/ProgressTracking', [
            'submissions' => $submissions,
            'metrics' => $metrics,
            'userRole' => $userRole,
            'canReset' => in_array($userRole, ['SUPERADMIN', 'ADMIN_PRINCIPAL']),
            'filters' => $request->only(['search', 'region_code', 'branch_id', 'stage']),
            'filterOptions' => $this->getFilterOptions($user),
        ]);
    }

    /**
     * Resets / Cancels inputan Admin Distributor (Reverts status to SE_SUBMITTED).
     * Melakukan CASCADING RESET: Mereset inputan Admin Distributor DAN inputan SPV Area (Rute JKS)
     * agar urutan alur tetap konsisten (Reset Admin -> Admin isi CustCode -> SPV isi JKS -> EDP Verifikasi).
     * Khusus role SUPERADMIN dan ADMIN_PRINCIPAL.
     */
    public function resetAdminInput(Request $request): RedirectResponse
    {
        $user = Auth::user();
        $userRole = $user->role ?? 'EDP_REGION';

        if (!in_array($userRole, ['SUPERADMIN', 'ADMIN_PRINCIPAL'])) {
            return back()->with('error', 'Akses Ditolak. Hanya Superadmin dan Admin Principal yang berhak mereset inputan Admin Distributor.');
        }

        $request->validate([
            'request_id' => 'required|uuid',
            'reason' => 'nullable|string',
        ]);

        try {
            $requestId = $request->input('request_id');
            $submission = DB::table('noo_submissions')->where('request_id', $requestId)->first();

            if (!$submission) {
                return back()->with('error', 'Data toko tidak ditemukan.');
            }

            if ($submission->status === 'APPROVED_EDP') {
                return back()->with('error', 'Toko sudah di-approve oleh EDP Principal. Lakukan Reset Approval EDP terlebih dahulu sebelum mereset inputan Admin/SPV.');
            }

            $nowText = now()->setTimezone('Asia/Jakarta')->format('Y-m-d H:i:s');
            $actorName = $user->name ?? $user->username ?? $user->email;
            $note = "[CASCADING RESET ADMIN & SPV INPUT pada {$nowText} oleh {$actorName}] " . ($request->input('reason') ?? 'Dibatalkan oleh Principal Admin');
            $updatedNotes = trim(($submission->admin_notes ?? '') . "\n" . $note);

            DB::table('noo_submissions')->where('request_id', $requestId)->update([
                // Reset Admin fields
                'custcode_distributor' => null,
                'approved_by_admin' => null,
                'pushed_to_spv_at' => null,
                'admin_notes' => $updatedNotes,
                // Cascading Reset SPV fields
                'norute' => null,
                'h1' => null, 'h2' => null, 'h3' => null, 'h4' => null, 'h5' => null, 'h6' => null, 'h7' => null,
                'm1' => null, 'm2' => null, 'm3' => null, 'm4' => null,
                'approval_spv_area' => null,
                'approved_by_spv' => null,
                'spv_submit_at' => null,
                'pushed_to_edp_at' => null,
                // Reset status to initial SE_SUBMITTED
                'status' => 'SE_SUBMITTED',
                'updated_at' => now(),
            ]);

            $this->logAction('RESET_ADMIN_INPUT', 'PROGRESS_TRACKING', "Mereset inputan Admin Distributor & SPV untuk toko: {$submission->nama_noo} ({$requestId})");

            return back()->with('success', "Inputan Admin Distributor & SPV Area untuk toko \"{$submission->nama_noo}\" berhasil di-cancel / di-reset ke status SE_SUBMITTED.");
        } catch (Throwable $e) {
            return back()->with('error', "Gagal mereset inputan Admin Distributor: {$e->getMessage()}");
        }
    }

    /**
     * Resets / Cancels inputan SPV Area (Reverts status to PUSHED_TO_SPV).
     * Khusus role SUPERADMIN dan ADMIN_PRINCIPAL.
     */
    public function resetSpvInput(Request $request): RedirectResponse
    {
        $user = Auth::user();
        $userRole = $user->role ?? 'EDP_REGION';

        if (!in_array($userRole, ['SUPERADMIN', 'ADMIN_PRINCIPAL'])) {
            return back()->with('error', 'Akses Ditolak. Hanya Superadmin dan Admin Principal yang berhak mereset inputan SPV Area.');
        }

        $request->validate([
            'request_id' => 'required|uuid',
            'reason' => 'nullable|string',
        ]);

        try {
            $requestId = $request->input('request_id');
            $submission = DB::table('noo_submissions')->where('request_id', $requestId)->first();

            if (!$submission) {
                return back()->with('error', 'Data toko tidak ditemukan.');
            }

            if ($submission->status === 'APPROVED_EDP') {
                return back()->with('error', 'Toko sudah di-approve oleh EDP Principal. Lakukan Reset Approval EDP terlebih dahulu sebelum mereset inputan SPV.');
            }

            $nowText = now()->setTimezone('Asia/Jakarta')->format('Y-m-d H:i:s');
            $actorName = $user->name ?? $user->username ?? $user->email;
            $note = "[RESET SPV INPUT pada {$nowText} oleh {$actorName}] " . ($request->input('reason') ?? 'Rute JKS dibatalkan oleh Principal Admin');
            $updatedNotes = trim(($submission->spv_notes ?? '') . "\n" . $note);

            DB::table('noo_submissions')->where('request_id', $requestId)->update([
                'norute' => null,
                'h1' => null, 'h2' => null, 'h3' => null, 'h4' => null, 'h5' => null, 'h6' => null, 'h7' => null,
                'm1' => null, 'm2' => null, 'm3' => null, 'm4' => null,
                'approval_spv_area' => null,
                'approved_by_spv' => null,
                'spv_submit_at' => null,
                'pushed_to_edp_at' => null,
                'status' => 'PUSHED_TO_SPV',
                'spv_notes' => $updatedNotes,
                'updated_at' => now(),
            ]);

            $this->logAction('RESET_SPV_INPUT', 'PROGRESS_TRACKING', "Mereset rute JKS & inputan SPV Area untuk toko: {$submission->nama_noo} ({$requestId})");

            return back()->with('success', "Inputan SPV Area (Rute JKS) untuk toko \"{$submission->nama_noo}\" berhasil di-cancel / di-reset ke status PUSHED_TO_SPV.");
        } catch (Throwable $e) {
            return back()->with('error', "Gagal mereset inputan SPV Area: {$e->getMessage()}");
        }
    }
}
