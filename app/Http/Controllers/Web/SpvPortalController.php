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

        // Cari cabang-cabang yang dinaungi SPV ini dari tabel master_spvs
        $salescode = $user->salesman_code ?? '';
        $myBranches = [];

        if (!empty($salescode)) {
            $myBranches = DB::table('master_spvs')
                ->where('salescode', $salescode)
                ->pluck('branch_id')
                ->toArray();
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
        } else if (!empty($user->branch_id)) {
            $myBranches = [$user->branch_id];
            $query->where('branch_id', $user->branch_id);
        } else {
            $query->whereRaw('1 = 0');
        }

        $submissions = $query->orderBy('created_at', 'desc')->get()->map(function ($item) {
            $item->photo_depan_url = $item->photo_depan_path ? asset('storage/' . $item->photo_depan_path) : null;
            $item->photo_dalam_url = $item->photo_dalam_path ? asset('storage/' . $item->photo_dalam_path) : null;
            $item->photo_ktp_url = $item->photo_ktp_path ? asset('storage/' . $item->photo_ktp_path) : null;
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
            'myBranches' => $branchesData,
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

            return back()->with('success', "Data toko {$submission->nama_noo} telah ditolak oleh SPV Area.");
        } catch (Throwable $e) {
            return back()->with('error', "Gagal menolak toko: {$e->getMessage()}");
        }
    }
}
