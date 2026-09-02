<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Enums\NooStatusEnum;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

/**
 * Controller Portal SPV Area (Private Server Domain Internal).
 * Menangani inbox toko dari Admin Distributor, pengisian jadwal rute H1-H7 & M1-M4,
 * persetujuan SPV (Approve ke EDP), dan penolakan SPV.
 */
class SpvPortalController extends Controller
{
    /**
     * Menampilkan daftar inbox toko untuk SPV Area.
     *
     * @param Request $request Request browser
     * @return Response Halaman Inertia Vue Inbox SPV
     */
    public function index(Request $request): Response|RedirectResponse
    {
        $user = session('spv_user') ?? $request->user();
        if (!$user) {
            return redirect()->route('spv_login.create');
        }

        // Live Query: Cari seluruh cabang yang dinaungi SPV ini secara real-time dari master_spvs
        $salescode = trim((string)($user->salesman_code ?? $user->salescode ?? $user->username ?? ''));
        $spvName = trim((string)($user->name ?? $user->nama ?? ''));
        $myBranches = [];

        if (!empty($salescode) || !empty($spvName)) {
            $myBranches = DB::table('master_spvs')
                ->where('is_active', true)
                ->whereNotNull('branch_id')
                ->where('branch_id', '!=', '')
                ->where(function ($q) use ($salescode, $spvName) {
                    if (!empty($salescode)) {
                        $q->where('salescode', 'ILIKE', $salescode);
                    }
                    if (!empty($spvName)) {
                        $q->orWhere('nama', 'ILIKE', $spvName);
                    }
                })
                ->pluck('branch_id')
                ->unique()
                ->toArray();
        }

        // Fallback jika tidak menemukan branch dari salescode, gunakan branch_id di session (bila ada)
        if (empty($myBranches) && !empty($user->branch_id)) {
            $myBranches = [$user->branch_id];
        }

        $query = DB::table('noo_submissions')
            ->whereIn('status', [
                NooStatusEnum::PUSHED_TO_SPV->value,
                NooStatusEnum::APPROVED_SPV->value,
                NooStatusEnum::REJECTED_SPV->value,
                NooStatusEnum::APPROVED_EDP->value,
                NooStatusEnum::REJECTED_EDP->value
            ]);

        if (!empty($myBranches)) {
            $query->whereIn('branch_id', $myBranches);
        } else {
            $query->whereRaw('1 = 0');
        }

        $statsBase = DB::table('noo_submissions')
            ->whereIn('status', [
                NooStatusEnum::PUSHED_TO_SPV->value,
                NooStatusEnum::APPROVED_SPV->value,
                NooStatusEnum::REJECTED_SPV->value,
                NooStatusEnum::APPROVED_EDP->value,
                NooStatusEnum::REJECTED_EDP->value
            ]);

        if (!empty($myBranches)) {
            $statsBase->whereIn('branch_id', $myBranches);
        } else if (!empty($user->branch_id)) {
            $statsBase->where('branch_id', $user->branch_id);
        } else {
            $statsBase->whereRaw('1 = 0');
        }

        $allSpvSubmissions = $statsBase->get();
        $stats = [
            'total' => $allSpvSubmissions->count(),
            'pendingSpv' => $allSpvSubmissions->filter(fn($i) => in_array($i->status, ['PUSHED_TO_SPV', 'ADMIN_APPROVED', NooStatusEnum::PUSHED_TO_SPV->value]))->count(),
            'approvedSpv' => $allSpvSubmissions->filter(fn($i) => in_array($i->status, ['APPROVED_SPV', 'APPROVED_BY_SPV', NooStatusEnum::APPROVED_SPV->value]))->count(),
            'approvedEdp' => $allSpvSubmissions->filter(fn($i) => in_array($i->status, ['APPROVED_EDP', 'EDP_APPROVED', NooStatusEnum::APPROVED_EDP->value]))->count(),
            'rejected' => $allSpvSubmissions->filter(fn($i) => in_array($i->status, ['REJECTED_SPV', 'SPV_REJECTED', 'REJECTED_EDP', 'EDP_REJECTED', 'ADMIN_REJECTED', 'REJECTED_ADMIN']))->count(),
        ];

        $perPage = (int) $request->input('per_page', 10);
        if ($perPage <= 0) {
            $perPage = 100000;
        }

        if ($request->filled('search')) {
            $s = trim((string) $request->input('search'));
            $query->where(function ($q) use ($s) {
                $q->where('nama_noo', 'ILIKE', "%{$s}%")
                  ->orWhere('salesman_name', 'ILIKE', "%{$s}%")
                  ->orWhere('salesman_code', 'ILIKE', "%{$s}%")
                  ->orWhere('branch_name', 'ILIKE', "%{$s}%")
                  ->orWhere('alamat_noo', 'ILIKE', "%{$s}%")
                  ->orWhere('custcode_distributor', 'ILIKE', "%{$s}%")
                  ->orWhere('code_noo_principal', 'ILIKE', "%{$s}%");
            });
        }

        if ($request->filled('status') && $request->input('status') !== 'ALL') {
            $st = $request->input('status');
            if ($st === 'REJECTED') {
                $query->whereIn('status', ['REJECTED_SPV', 'SPV_REJECTED', 'REJECTED_EDP', 'EDP_REJECTED', 'ADMIN_REJECTED', 'REJECTED_ADMIN']);
            } else {
                $query->where('status', $st);
            }
        }

        if ($request->filled('branch_id') && $request->input('branch_id') !== 'ALL') {
            $query->where('branch_id', $request->input('branch_id'));
        }

        $query->orderByRaw("CASE WHEN status = 'PUSHED_TO_SPV' THEN 0 ELSE 1 END ASC")
              ->orderByRaw("COALESCE(pushed_to_spv_at, submitted_at, created_at) DESC");

        $formatPhoto = function ($path) {
            if (empty($path)) return null;
            $cleanPath = ltrim(str_replace('storage/', '', $path), '/');
            return url('/media-photo/' . $cleanPath);
        };

        $submissions = $query->paginate($perPage)->withQueryString()->through(function ($item) use ($formatPhoto) {
            $item->photo_depan_url = $formatPhoto($item->photo_depan_path ?? null);
            $item->photo_dalam_url = $formatPhoto($item->photo_dalam_path ?? null);
            $item->photo_ktp_url = $formatPhoto($item->photo_ktp_path ?? null);
            return $item;
        });

        $branchesData = [];
        if (!empty($myBranches)) {
            $branchesData = DB::table('master_branches')
                ->whereIn('branch_id', $myBranches)
                ->select('branch_id', 'branch_name')
                ->orderBy('branch_id')
                ->get()
                ->toArray();
        }

        return Inertia::render('Spv/Inbox', [
            'submissions' => $submissions,
            'stats' => $stats,
            'myBranches' => $branchesData,
            'filters' => $request->only(['search', 'status', 'branch_id']),
        ]);
    }

    /**
     * Memproses persetujuan (Approve) toko oleh SPV & pengisian rute H1-H7 / M1-M4.
     *
     * @param Request $request Request berisi data rute & request_id
     * @return RedirectResponse Redirect dengan pesan flash
     */
    public function approve(Request $request): RedirectResponse
    {
        $request->validate([
            'request_id' => 'required|uuid',
            'norute' => 'nullable|string',
            'h1' => 'nullable|string', 'h2' => 'nullable|string', 'h3' => 'nullable|string',
            'h4' => 'nullable|string', 'h5' => 'nullable|string', 'h6' => 'nullable|string', 'h7' => 'nullable|string',
            'm1' => 'nullable|string', 'm2' => 'nullable|string', 'm3' => 'nullable|string', 'm4' => 'nullable|string',
            'spv_notes' => 'nullable|string',
        ]);

        try {
            $requestId = $request->input('request_id');
            $submission = DB::table('noo_submissions')->where('request_id', $requestId)->first();

            if (!$submission) {
                return back()->with('error', 'Data toko tidak ditemukan.');
            }

            $user = session('spv_user') ?? $request->user();
            if (!$user) {
                return redirect()->route('spv_login.create')->withErrors(['username' => 'Sesi SPV telah berakhir.']);
            }
            $userName = $user->name ?? $user->spv_name ?? $user->salesman_name ?? 'SPV Area';

            $toYorT = fn($val) => (strtoupper((string)$val) === 'Y' || strtoupper((string)$val) === 'YES') ? 'Y' : 'T';

            DB::table('noo_submissions')->where('request_id', $requestId)->update([
                'norute' => '01',
                'h1' => $toYorT($request->input('h1')), 'h2' => $toYorT($request->input('h2')), 'h3' => $toYorT($request->input('h3')),
                'h4' => $toYorT($request->input('h4')), 'h5' => $toYorT($request->input('h5')), 'h6' => $toYorT($request->input('h6')), 'h7' => $toYorT($request->input('h7')),
                'm1' => $toYorT($request->input('m1')), 'm2' => $toYorT($request->input('m2')), 'm3' => $toYorT($request->input('m3')), 'm4' => $toYorT($request->input('m4')),
                'spv_notes' => $request->input('spv_notes'),
                'approval_spv_area' => 'YES',
                'approved_by_spv' => $userName,
                'spv_submit_at' => now(),
                'pushed_to_edp_at' => now(),
                'status' => NooStatusEnum::APPROVED_SPV->value,
                'updated_at' => now(),
            ]);

            DB::table('activity_logs')->insert([
                'username' => $userName,
                'user_role' => 'SPV_AREA',
                'action' => 'APPROVE_SPV',
                'module' => 'SPV_PORTAL',
                'description' => "Supervisor Area {$userName} menyetujui rute & mendorong toko {$submission->nama_noo} ke EDP Principal.",
                'ip_address' => $request->ip(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return back()->with('success', "Data toko {$submission->nama_noo} berhasil disetujui & didorong ke Portal EDP.");
        } catch (Throwable $e) {
            return back()->with('error', "Gagal approve SPV: {$e->getMessage()}");
        }
    }

    /**
     * Memproses penolakan (Reject) toko oleh SPV Area.
     *
     * @param Request $request Request berisi request_id & reject_reason
     * @return RedirectResponse Redirect dengan pesan flash
     */
    public function reject(Request $request): RedirectResponse
    {
        $request->validate([
            'request_id' => 'required|uuid',
            'reject_reason' => 'required|string',
        ]);

        try {
            $requestId = $request->input('request_id');
            $submission = DB::table('noo_submissions')->where('request_id', $requestId)->first();

            if (!$submission) {
                return back()->with('error', 'Data toko tidak ditemukan.');
            }

            if (!empty($submission->pushed_to_edp_at)) {
                return back()->with('error', 'Data toko sudah terlanjur terdorong ke EDP, tidak bisa direject oleh SPV lagi.');
            }

            $user = session('spv_user') ?? $request->user();
            if (!$user) {
                return redirect()->route('spv_login.create')->withErrors(['username' => 'Sesi SPV telah berakhir.']);
            }
            $userName = $user->name ?? $user->spv_name ?? $user->salesman_name ?? 'SPV Area';
            $rejectReason = trim((string) $request->input('reject_reason'));

            DB::table('noo_submissions')->where('request_id', $requestId)->update([
                'approval_spv_area' => 'NO',
                'approved_by_spv' => $userName,
                'reject_reason' => $rejectReason,
                'status' => NooStatusEnum::REJECTED_SPV->value,
                'spv_submit_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('activity_logs')->insert([
                'username' => $userName,
                'user_role' => 'SPV_AREA',
                'action' => 'REJECT_SPV',
                'module' => 'SPV_PORTAL',
                'description' => "Supervisor Area {$userName} menolak toko {$submission->nama_noo} (Alasan: {$rejectReason}).",
                'ip_address' => $request->ip(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return back()->with('success', "Data toko {$submission->nama_noo} telah ditolak oleh Supervisor Area.");
        } catch (Throwable $e) {
            return back()->with('error', "Gagal menolak toko: {$e->getMessage()}");
        }
    }
}
