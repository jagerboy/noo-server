<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Controller Autentikasi Portal NOO+ (Login Username & Password langsung dari tabel users).
 */
class EdpLoginController extends Controller
{
    public function create(): Response|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route('edp.dashboard');
        }
        return Inertia::render('Auth/EdpLogin');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        $username = strtolower(trim($request->input('username')));
        $password = trim($request->input('password'));

        // Autentikasi langsung ke tabel users (bisa via username atau region_code)
        $user = User::where(function ($query) use ($username) {
            $query->where('username', $username)
                  ->orWhere('email', $username)
                  ->orWhere('region_code', strtoupper($username));
        })
        ->where('is_active', true)
        ->first();

        // Cek kecocokan user dan password
        if (!$user) {
            return back()->withErrors([
                'username' => 'USERNAME atau Password yang Anda masukkan salah.',
            ]);
        }

        // Cek kecocokan password (mendukung Plain Text & Bcrypt Hash secara aman)
        $storedPassword = (string) $user->password;
        $passwordValid = ($storedPassword === $password);

        if (!$passwordValid && (str_starts_with($storedPassword, '$2y$') || str_starts_with($storedPassword, '$2a$') || str_starts_with($storedPassword, '$2b$'))) {
            try {
                $passwordValid = Hash::check($password, $storedPassword);
            } catch (\Throwable $e) {
                $passwordValid = false;
            }
        }

        if (!$passwordValid) {
            return back()->withErrors([
                'username' => 'USERNAME atau Password yang Anda masukkan salah.',
            ]);
        }

        $remember = (bool) $request->input('remember', false);
        Auth::login($user, $remember);
        $request->session()->regenerate();

        // Audit Log
        DB::table('activity_logs')->insert([
            'username' => $user->username ?? $user->name ?? $user->email,
            'user_role' => $user->role ?? 'EDP_REGION',
            'action' => 'LOGIN',
            'module' => 'AUTHENTICATION',
            'description' => "Pengguna {$user->name} ({$user->role}) berhasil login ke Portal NOO+.",
            'ip_address' => $request->ip(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return redirect()->intended(route('edp.dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        if (Auth::check()) {
            $user = Auth::user();
            DB::table('activity_logs')->insert([
                'username' => $user->username ?? $user->name ?? $user->email,
                'user_role' => $user->role ?? 'UNKNOWN',
                'action' => 'LOGOUT',
                'module' => 'AUTHENTICATION',
                'description' => "Pengguna {$user->name} logout dari sistem.",
                'ip_address' => $request->ip(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('edp_login.create');
    }
}
