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
 * Controller Portal Admin Distributor (Domain Publik).
 * Menangani inbox toko masuk dari SE, pengisian custcode_distributor,
 * admin_notes, penolakan toko, dan submit data toko ke Portal SPV.
 */
class AdminDistributorController extends Controller
{
    /**
     * Menampilkan daftar inbox toko untuk Admin Distributor.
     *
     * @param Request $request Request browser
     * @return Response Halaman Inertia Vue Inbox Admin
     */
    public function index(Request $request): Response|RedirectResponse
    {
        $user = session('distributor_user') ?? $request->user();
        if (!$user) {
            return redirect()->route('distributor_login.create');
        }
        $branchId = $user->branch_id ?? null;

        $query = DB::table('noo_submissions');
        if (!empty($branchId)) {
            $query->where('branch_id', $branchId);
        } else {
            // Jika branch_id tidak terdefinisi, jangan tampilkan data cabang lain
            $query->whereRaw('1 = 0');
        }

        $submissions = $query->orderBy('created_at', 'desc')->get()->map(function ($item) {
            $item->photo_depan_url = $item->photo_depan_path ? asset('storage/' . $item->photo_depan_path) : null;
            $item->photo_dalam_url = $item->photo_dalam_path ? asset('storage/' . $item->photo_dalam_path) : null;
            $item->photo_ktp_url = $item->photo_ktp_path ? asset('storage/' . $item->photo_ktp_path) : null;
            return $item;
        });

        return Inertia::render('Admin/Inbox', [
            'submissions' => $submissions,
            'userBranch' => $branchId,
        ]);
    }

    /**
     * Memproses submit toko dari Admin Distributor ke Portal SPV Area.
     *
     * @param Request $request Request berisi request_id & custcode_distributor
     * @return RedirectResponse Redirect dengan pesan flash
     */
    public function submitToSpv(Request $request): RedirectResponse
    {
        $request->validate([
            'request_id' => 'required|uuid',
            'custcode_distributor' => 'required|string|max:50',
            'admin_notes' => 'nullable|string',
        ]);

        try {
            $requestId = $request->input('request_id');
            $submission = DB::table('noo_submissions')->where('request_id', $requestId)->first();

            if (!$submission) {
                return back()->with('error', 'Data toko tidak ditemukan.');
            }

            if ($submission->photo_status !== 'READY') {
                return back()->with('error', 'Foto toko belum lengkap (minimal 3/3 foto terupload).');
            }

            if ($submission->status !== NooStatusEnum::SE_SUBMITTED->value) {
                return back()->with('error', "Data toko sudah diproses dengan status: {$submission->status}");
            }

            $user = session('distributor_user') ?? $request->user();
            if (!$user) {
                return redirect()->route('distributor_login.create')->withErrors(['pin_branch' => 'Sesi login telah berakhir.']);
            }
            $userName = $user->name ?? $user->branch_name ?? ("Admin Cabang " . ($submission->branch_id ?? ''));

            DB::table('noo_submissions')->where('request_id', $requestId)->update([
                'custcode_distributor' => trim((string) $request->input('custcode_distributor')),
                'admin_notes' => $request->input('admin_notes'),
                'approved_by_admin' => $userName,
                'status' => NooStatusEnum::PUSHED_TO_SPV->value,
                'pushed_to_spv_at' => now(),
                'updated_at' => now(),
            ]);

            return back()->with('success', "Data toko {$submission->nama_noo} berhasil disubmit ke Portal SPV Area.");
        } catch (Throwable $e) {
            return back()->with('error', "Gagal submit ke SPV: {$e->getMessage()}");
        }
    }

    /**
     * Memproses penolakan (Reject) toko oleh Admin Distributor.
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

            $user = session('distributor_user') ?? $request->user();
            if (!$user) {
                return redirect()->route('distributor_login.create')->withErrors(['pin_branch' => 'Sesi login telah berakhir.']);
            }
            $userName = $user->name ?? $user->branch_name ?? 'Admin Distributor';
            $nowText = now()->setTimezone('Asia/Jakarta')->format('Y-m-d H:i:s');
            $rejectNote = "{$request->input('reject_reason')} : [ADMIN_REJECTED {$nowText} oleh {$userName}]";

            DB::table('noo_submissions')->where('request_id', $requestId)->update([
                'status' => NooStatusEnum::ADMIN_REJECTED->value,
                'admin_notes' => $rejectNote,
                'approved_by_admin' => $userName,
                'updated_at' => now(),
            ]);

            return back()->with('success', "Data toko {$submission->nama_noo} telah ditolak oleh Admin Distributor.");
        } catch (Throwable $e) {
            return back()->with('error', "Gagal menolak toko: {$e->getMessage()}");
        }
    }
}
