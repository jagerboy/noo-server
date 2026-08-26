<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

/**
 * Controller penanganan Login Khusus Admin Distributor (Public Domain).
 * Melakukan otentikasi bertingkat memilih Region -> Entity Principal -> Branch -> PIN Branch
 * yang terhubung ke database master terpusat (master_branches).
 */
class DistributorLoginController extends Controller
{
    /**
     * Menampilkan halaman UI Login Admin Distributor bertingkat.
     *
     * @return Response Halaman Inertia Vue DistributorLogin
     */
    public function create(): Response|RedirectResponse
    {
        if (session()->has('distributor_user')) {
            return redirect()->route('admin.inbox');
        }
        return Inertia::render('Auth/DistributorLogin', [
            'bootstrapData' => $this->getBootstrapDataPayload(),
        ]);
    }

    /**
     * Helper privat penyusun data bootstrap dropdown login.
     */
    public function getBootstrapDataPayload(): array
    {
        try {
            $branches = DB::table('master_branches')
                ->where('is_active', true)
                ->get();

            // Daftar 6 Opsi Principal Group (Sub-Grup Daerah Principal)
            $principalGroupsMap = [
                'ASW_SUMATERA' => ['code' => 'ASW_SUMATERA', 'label' => 'ASW SUMATERA', 'prefix' => 'ASWSUM'],
                'ASW_JAWA'     => ['code' => 'ASW_JAWA',     'label' => 'ASW JAWA',     'prefix' => 'ASWJWA'],
                'ASW_PULAU'    => ['code' => 'ASW_PULAU',    'label' => 'ASW PULAU',    'prefix' => 'ASWPUL'],
                'INA_JAWA'     => ['code' => 'INA_JAWA',     'label' => 'INA JAWA',     'prefix' => 'INAJWA'],
                'INA_PULAU'    => ['code' => 'INA_PULAU',    'label' => 'INA PULAU',    'prefix' => 'INAPUL'],
                'INA_SUMATERA' => ['code' => 'INA_SUMATERA', 'label' => 'INA SUMATERA', 'prefix' => 'INASUM'],
            ];

            $regionsByPrincipalGroup = [
                'ASW_SUMATERA' => [],
                'ASW_JAWA'     => [],
                'ASW_PULAU'    => [],
                'INA_JAWA'     => [],
                'INA_PULAU'    => [],
                'INA_SUMATERA' => [],
            ];

            $entitiesByRegion = [];
            $branchesByRegionEntity = [];

            foreach ($branches as $b) {
                $regionCode = trim($b->region_code);
                $regionName = trim($b->region_name ?? $regionCode);
                $entityCode = trim($b->entity_code_principal);
                $entityName = trim($b->entity_name_principal ?? $entityCode);
                $branchId = trim($b->branch_id);
                $branchName = trim($b->branch_name ?? $branchId);

                // Tentukan Principal Group berdasarkan prefix & substring region_code (misal ASWJWA1 -> ASW_JAWA)
                $cleanRegion = strtoupper($regionCode);
                $groupCode = null;

                if (str_contains($cleanRegion, 'ASW')) {
                    if (str_contains($cleanRegion, 'SUM')) $groupCode = 'ASW_SUMATERA';
                    elseif (str_contains($cleanRegion, 'JWA') || str_contains($cleanRegion, 'JAWA')) $groupCode = 'ASW_JAWA';
                    elseif (str_contains($cleanRegion, 'PUL')) $groupCode = 'ASW_PULAU';
                } elseif (str_contains($cleanRegion, 'INA')) {
                    if (str_contains($cleanRegion, 'SUM')) $groupCode = 'INA_SUMATERA';
                    elseif (str_contains($cleanRegion, 'JWA') || str_contains($cleanRegion, 'JAWA')) $groupCode = 'INA_JAWA';
                    elseif (str_contains($cleanRegion, 'PUL')) $groupCode = 'INA_PULAU';
                }

                if (!$groupCode) {
                    foreach ($principalGroupsMap as $gCode => $gData) {
                        if (str_starts_with($cleanRegion, $gData['prefix'])) {
                            $groupCode = $gCode;
                            break;
                        }
                    }
                }

                if (!$groupCode) {
                    $groupCode = 'ASW_SUMATERA';
                }

                // Tambahkan Region ke Group-nya jika belum ada
                if (!isset($regionsByPrincipalGroup[$groupCode][$regionCode])) {
                    $regionsByPrincipalGroup[$groupCode][$regionCode] = [
                        'code' => $regionCode,
                        'label' => "{$regionCode} - {$regionName}"
                    ];
                }

                // Entity By Region
                if (!isset($entitiesByRegion[$regionCode])) {
                    $entitiesByRegion[$regionCode] = [];
                }
                $entitiesByRegion[$regionCode][$entityCode] = [
                    'code' => $entityCode,
                    'label' => "{$entityCode} - {$entityName}"
                ];

                // Branch By Region & Entity
                $reKey = "{$regionCode}||{$entityCode}";
                if (!isset($branchesByRegionEntity[$reKey])) {
                    $branchesByRegionEntity[$reKey] = [];
                }
                $branchesByRegionEntity[$reKey][$branchId] = [
                    'code' => $branchId,
                    'label' => "{$branchId} - {$branchName}",
                    'branch_id' => $branchId,
                    'branch_name' => $branchName,
                ];
            }

            // Formatting list principal groups
            $principalGroupsList = array_values($principalGroupsMap);

            // Sorting regions dalam tiap principal group
            foreach ($regionsByPrincipalGroup as $gCode => $rMap) {
                $rList = array_values($rMap);
                usort($rList, fn($a, $b) => strcmp($a['label'], $b['label']));
                $regionsByPrincipalGroup[$gCode] = $rList;
            }

            // Sorting entities
            foreach ($entitiesByRegion as $reg => $entities) {
                $eList = array_values($entities);
                usort($eList, fn($a, $b) => strcmp($a['label'], $b['label']));
                $entitiesByRegion[$reg] = $eList;
            }

            // Sorting branches
            foreach ($branchesByRegionEntity as $reKey => $branchList) {
                $bList = array_values($branchList);
                usort($bList, fn($a, $b) => strcmp($a['label'], $b['label']));
                $branchesByRegionEntity[$reKey] = $bList;
            }

            return [
                'ok' => true,
                'principals' => $principalGroupsList,
                'regionsByPrincipalGroup' => $regionsByPrincipalGroup,
                'entitiesByRegion' => $entitiesByRegion,
                'branchesByRegionEntity' => $branchesByRegionEntity,
            ];
        } catch (Throwable $e) {
            return [
                'ok' => false,
                'principals' => [],
                'regionsByPrincipalGroup' => [],
                'entitiesByRegion' => [],
                'branchesByRegionEntity' => [],
            ];
        }
    }

    /**
     * Menyediakan data dropdown bertingkat (Principal Group, Region, Entity, Branch) untuk UI Login.
     *
     * @return JsonResponse Data opsi dropdown login
     */
    public function getBootstrapData(): JsonResponse
    {
        return response()->json($this->getBootstrapDataPayload());
    }

    /**
     * Memproses otentikasi login PIN Branch Admin Distributor.
     *
     * @param Request $request Request login bertingkat
     * @return RedirectResponse Redirect ke inbox admin jika sukses
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'region_code' => 'required|string',
            'entity_code_principal' => 'required|string',
            'branch_id' => 'required|string',
            'pin_branch' => 'required|string',
        ]);

        $regionCode = trim($request->input('region_code'));
        $entityCode = trim($request->input('entity_code_principal'));
        $branchId = trim($request->input('branch_id'));
        $pinBranch = trim($request->input('pin_branch'));

        // Cek kombinasi cabang & PIN di database master
        $branch = DB::table('master_branches')
            ->where('region_code', $regionCode)
            ->where('entity_code_principal', $entityCode)
            ->where('branch_id', $branchId)
            ->where('is_active', true)
            ->first();

        if (!$branch) {
            return back()->withErrors(['pin_branch' => 'Kombinasi Region, Entity, atau Branch tidak valid.']);
        }

        if ($branch->pin_branch !== $pinBranch) {
            return back()->withErrors(['pin_branch' => 'PIN Branch yang Anda masukkan salah.']);
        }

        // Simpan Sesi Terisolasi khusus Admin Distributor
        session(['distributor_user' => (object)[
            'branch_id' => $branchId,
            'name' => $branch->branch_name,
            'role' => 'ADMIN_DISTRIBUTOR',
            'region_code' => $regionCode,
            'entity_code_principal' => $entityCode,
        ]]);

        $request->session()->regenerate();

        // Audit Log Activity
        DB::table('activity_logs')->insert([
            'username' => $branch->branch_name ?? $branchId,
            'user_role' => 'ADMIN_DISTRIBUTOR',
            'action' => 'LOGIN',
            'module' => 'AUTHENTICATION',
            'description' => "Pengguna Admin Distributor {$branch->branch_name} ({$branchId}) berhasil login ke Portal Admin Distributor.",
            'ip_address' => $request->ip(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return redirect()->intended(route('admin.inbox'));
    }

    /**
     * Memproses logout sesi Admin Distributor.
     *
     * @param Request $request
     * @return RedirectResponse Redirect ke halaman /distributor-login
     */
    public function destroy(Request $request): RedirectResponse
    {
        $distUser = session('distributor_user');
        if ($distUser) {
            DB::table('activity_logs')->insert([
                'username' => $distUser->name ?? $distUser->branch_id ?? 'Admin Distributor',
                'user_role' => 'ADMIN_DISTRIBUTOR',
                'action' => 'LOGOUT',
                'module' => 'AUTHENTICATION',
                'description' => "Pengguna Admin Distributor " . ($distUser->name ?? '') . " logout dari sistem.",
                'ip_address' => $request->ip(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        session()->forget('distributor_user');
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('distributor_login.create');
    }
}
