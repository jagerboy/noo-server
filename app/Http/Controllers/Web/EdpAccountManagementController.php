<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class EdpAccountManagementController extends Controller
{
    private function syncSequence(): void
    {
        try {
            DB::statement("SELECT setval('users_id_seq', COALESCE((SELECT MAX(id) FROM users), 1))");
        } catch (Throwable $e) {}
    }

    public function index(Request $request): Response
    {
        $currentUser = Auth::user();
        $userRole = $currentUser->role ?? 'EDP_REGION';
        if ($userRole !== 'SUPERADMIN') {
            abort(403, 'Akses Ditolak. Halaman Manajemen Akun & User Role Manager hanya dapat diakses oleh Superadmin.');
        }

        $query = DB::table('users')
            ->select('id', 'username', 'name', 'name as nama', 'role', 'region_code', 'entity_code_principal', 'is_active');

        if ($request->filled('search')) {
            $s = $request->input('search');
            $query->where(function ($q) use ($s) {
                $q->where('username', 'ILIKE', "%{$s}%")
                  ->orWhere('name', 'ILIKE', "%{$s}%")
                  ->orWhere('role', 'ILIKE', "%{$s}%")
                  ->orWhere('region_code', 'ILIKE', "%{$s}%");
            });
        }

        $perPage = (int) $request->input('per_page', 10);
        if ($perPage <= 0) {
            $perPage = 100000;
        }

        $accounts = $query->orderBy('id', 'desc')->paginate($perPage)->withQueryString();
        
        $regions = DB::table('master_branches')
            ->select('region_code', 'region_name')
            ->distinct()
            ->whereNotNull('region_code')
            ->orderBy('region_code')
            ->get();

        // Definisi Principal Area standar
        $defaultPrincipalAreasMap = [
            'ASWSUM' => ['region_code' => 'ASWSUM', 'region_name' => 'Principal Area ASW SUMATERA (ASWSUM)'],
            'ASWJWA' => ['region_code' => 'ASWJWA', 'region_name' => 'Principal Area ASW JAWA (ASWJWA)'],
            'ASWPUL' => ['region_code' => 'ASWPUL', 'region_name' => 'Principal Area ASW PULAU (ASWPUL)'],
            'INAJWA' => ['region_code' => 'INAJWA', 'region_name' => 'Principal Area INA JAWA (INAJWA)'],
            'INAPUL' => ['region_code' => 'INAPUL', 'region_name' => 'Principal Area INA PULAU (INAPUL)'],
            'INASUM' => ['region_code' => 'INASUM', 'region_name' => 'Principal Area INA SUMATERA (INASUM)'],
        ];

        // Mengelompokkan prefiks Region dari DB jika ada tambahan
        $principalAreasMap = $defaultPrincipalAreasMap;
        foreach ($regions as $r) {
            $code = (string) $r->region_code;
            $prefix = preg_replace('/\d+$/', '', $code);
            if (!empty($prefix) && !isset($principalAreasMap[$prefix])) {
                $cleanName = trim(preg_replace('/[0-9\-\.\_]+$/', '', (string) $r->region_name));
                $principalAreasMap[$prefix] = [
                    'region_code' => $prefix,
                    'region_name' => "Principal Area {$cleanName} ({$prefix})",
                ];
            }
        }
        $principalAreas = array_values($principalAreasMap);

        // Tambahkan single region standar jika belum ada di database
        $defaultSingleRegionsMap = [
            'ASWSUM1' => 'ASW SUMATERA 1',
            'ASWSUM2' => 'ASW SUMATERA 2',
            'ASWSUM3' => 'ASW SUMATERA 3',
            'ASWJWA1' => 'ASW JAWA 1',
            'ASWJWA2' => 'ASW JAWA 2',
            'ASWPUL1' => 'ASW PULAU 1',
            'INAJWA1' => 'INA JAWA 1',
            'INAJWA2' => 'INA JAWA 2',
            'INAPUL1' => 'INA PULAU 1',
            'INASUM1' => 'INA SUMATERA 1',
            'INASUM2' => 'INA SUMATERA 2',
        ];

        $existingRegionCodes = $regions->pluck('region_code')->toArray();
        $regionsArray = $regions->toArray();

        foreach ($defaultSingleRegionsMap as $code => $name) {
            if (!in_array($code, $existingRegionCodes)) {
                $regionsArray[] = (object) [
                    'region_code' => $code,
                    'region_name' => $name,
                ];
            }
        }

        // Urutkan regions berdasarkan region_code
        usort($regionsArray, function ($a, $b) {
            return strcmp((string)$a->region_code, (string)$b->region_code);
        });

        $regions = collect($regionsArray);

        return Inertia::render('Edp/AccountManagement', [
            'accounts' => $accounts,
            'regions' => $regions,
            'principalAreas' => $principalAreas,
            'filters' => $request->only(['search']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $currentUser = Auth::user();
        $userRole = $currentUser->role ?? 'EDP_REGION';
        if ($userRole !== 'SUPERADMIN') {
            return back()->withErrors(['error' => 'Akses ditolak. Hanya Superadmin yang berhak membuat akun baru.']);
        }

        $request->validate([
            'username' => 'required|string|unique:users,username',
            'password' => 'required|string|min:4',
            'nama' => 'required|string',
            'role' => 'required|string|in:SUPERADMIN,ADMIN_PRINCIPAL,EDP_REGION',
            'region_code' => 'nullable|string',
            'entity_code_principal' => 'nullable|string',
        ]);

        $username = strtolower(trim($request->username));
        $this->syncSequence();

        DB::table('users')->insert([
            'username' => $username,
            'name' => $request->nama,
            'email' => "{$username}@noo.portal",
            'password' => Hash::make(trim($request->password)),
            'role' => $request->role,
            'region_code' => $request->region_code ? strtoupper($request->region_code) : null,
            'entity_code_principal' => $request->entity_code_principal ? strtoupper($request->entity_code_principal) : null,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('activity_logs')->insert([
            'username' => $currentUser->username ?? $currentUser->name,
            'user_role' => $currentUser->role ?? 'SUPERADMIN',
            'action' => 'CREATE_USER',
            'module' => 'ACCOUNT_MANAGEMENT',
            'description' => "Membuat Akun Pengguna Baru: {$username} dengan Peran {$request->role}",
            'ip_address' => $request->ip(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return back()->with('success', "Akun {$username} berhasil dibuat.");
    }

    public function update(Request $request, $id): RedirectResponse
    {
        $currentUser = Auth::user();
        if (($currentUser->role ?? '') !== 'SUPERADMIN') {
            return back()->withErrors(['error' => 'Hanya Superadmin yang berhak mengedit akun.']);
        }

        $request->validate([
            'nama' => 'required|string',
            'role' => 'required|string|in:SUPERADMIN,ADMIN_PRINCIPAL,EDP_REGION',
            'region_code' => 'nullable|string',
            'is_active' => 'required|boolean',
        ]);

        $data = [
            'name' => $request->nama,
            'role' => $request->role,
            'region_code' => $request->region_code ? strtoupper($request->region_code) : null,
            'is_active' => $request->is_active,
            'updated_at' => now(),
        ];

        if ($request->filled('password')) {
            $data['password'] = Hash::make(trim($request->password));
        }

        DB::table('users')->where('id', $id)->update($data);

        return back()->with('success', "Data akun berhasil diperbarui.");
    }

    public function destroy($id): RedirectResponse
    {
        $currentUser = Auth::user();
        if (($currentUser->role ?? '') !== 'SUPERADMIN') {
            return back()->withErrors(['error' => 'Hanya Superadmin yang berhak menghapus akun.']);
        }

        DB::table('users')->where('id', $id)->delete();

        return back()->with('success', "Akun berhasil dihapus.");
    }
}
