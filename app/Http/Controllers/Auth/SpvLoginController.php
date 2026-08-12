<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Controller Authentifikasi khusus SPV Area (Login Username & Password dari master_spvs).
 * Menggunakan isolasi Sesi Khusus (spv_user) agar tidak bentrok dengan Auth User Web Portal NOO+.
 */
class SpvLoginController extends Controller
{
    public function create(): Response|RedirectResponse
    {
        if (session()->has('spv_user')) {
            return redirect()->route('spv.inbox');
        }
        $allSubmissions = DB::table('noo_submissions')->get();
        $metrics = [
            'total' => $allSubmissions->count(),
            'pendingAdmin' => $allSubmissions->where('status', 'SE_SUBMITTED')->count(),
            'pushedToSpv' => $allSubmissions->where('status', 'PUSHED_TO_SPV')->count(),
            'approvedSpv' => $allSubmissions->whereIn('status', ['APPROVED_SPV', 'PUSHED_TO_EDP'])->count(),
            'approvedEdp' => $allSubmissions->where('status', 'APPROVED_EDP')->count(),
            'rejected' => $allSubmissions->whereIn('status', ['ADMIN_REJECTED', 'SPV_REJECTED', 'REJECTED_SPV', 'EDP_REJECTED', 'REJECTED_EDP'])->count(),
        ];

        return Inertia::render('Auth/SpvLogin', [
            'metrics' => $metrics,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        $username = strtoupper(trim($request->input('username')));
        $password = trim($request->input('password'));

        $spvFirst = DB::table('master_spvs')
            ->where('salescode', $username)
            ->where('is_active', true)
            ->first();

        // Cek kecocokan password (mendukung Plain Text & Bcrypt Hash secara aman)
        $storedPassword = (string) $spvFirst->password;
        $passwordValid = ($storedPassword === $password);

        if (!$passwordValid && (str_starts_with($storedPassword, '$2y$') || str_starts_with($storedPassword, '$2a$') || str_starts_with($storedPassword, '$2b$'))) {
            try {
                $passwordValid = \Illuminate\Support\Facades\Hash::check($password, $storedPassword);
            } catch (\Throwable $e) {
                $passwordValid = false;
            }
        }

        if (!$passwordValid) {
            return back()->withErrors([
                'username' => 'Username atau Password SPV yang Anda masukkan salah.',
            ]);
        }

        // Simpan Sesi Terisolasi khusus SPV Area
        session(['spv_user' => (object)[
            'salesman_code' => $username,
            'name' => $spvFirst->nama,
            'nama' => $spvFirst->nama,
            'branch_id' => $spvFirst->branch_id,
            'role' => 'SPV_AREA',
        ]]);

        $request->session()->regenerate();

        return redirect()->intended(route('spv.inbox'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        $request->session()->forget('spv_user');
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('spv_login.create');
    }
}
