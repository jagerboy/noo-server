<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class EdpDashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $user = Auth::user();
        $userRole = $user->role ?? 'EDP_REGION';
        $userRegion = $user->region_code ?? null;
        $userEntity = $user->entity_code_principal ?? null;

        // Base Query NOO Submissions
        $query = DB::table('noo_submissions');

        // Apply Data Isolation based on Role
        if ($userRole !== 'SUPERADMIN' && !empty($userRegion)) {
            $query->where('region_code', 'LIKE', "{$userRegion}%");
        }
        if ($userRole === 'ADMIN_PRINCIPAL' && !empty($userEntity)) {
            $query->where(function ($q) use ($userEntity) {
                $q->where('principal', 'ILIKE', "%{$userEntity}%")
                  ->orWhere('principal_code', $userEntity);
            });
        }

        // Apply Interactive Filters from Request
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
            $query->where('branch_id', $request->input('branch_id'));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $globalYear = $request->input('year');
        if ($request->filled('year')) {
            $query->whereYear(DB::raw("COALESCE(submitted_at, created_at)"), $globalYear);
        }

        $c1Year = $request->input('chart1_year', $globalYear);
        $c2Year = $request->input('chart2_year', $globalYear);
        $c3Year = $request->input('chart3_year', $globalYear);
        $c4Year = $request->input('chart4_year', $globalYear);

        // 1. Single Aggregation Query for 9 Metric Cards (< 5ms)
        $m = (clone $query)->selectRaw("
            COUNT(*) as total_submitted_se,
            COUNT(CASE WHEN status = 'SE_SUBMITTED' THEN 1 END) as pending_admin,
            COUNT(CASE WHEN status IN ('PUSHED_TO_SPV', 'APPROVED_SPV', 'PUSHED_TO_EDP', 'APPROVED_EDP', 'INJECTED') THEN 1 END) as approved_admin,
            COUNT(CASE WHEN status IN ('ADMIN_REJECTED', 'REJECTED_ADMIN') THEN 1 END) as rejected_admin,
            COUNT(CASE WHEN status = 'PUSHED_TO_SPV' THEN 1 END) as pending_spv,
            COUNT(CASE WHEN status IN ('APPROVED_SPV', 'PUSHED_TO_EDP', 'APPROVED_EDP', 'INJECTED') THEN 1 END) as approved_spv,
            COUNT(CASE WHEN status IN ('SPV_REJECTED', 'REJECTED_SPV') THEN 1 END) as rejected_spv,
            COUNT(CASE WHEN status IN ('APPROVED_SPV', 'PUSHED_TO_EDP') THEN 1 END) as pending_principal,
            COUNT(CASE WHEN status IN ('APPROVED_EDP', 'INJECTED', 'EDP_APPROVED') THEN 1 END) as approved_principal,
            COUNT(CASE WHEN status IN ('EDP_REJECTED', 'REJECTED_EDP') THEN 1 END) as rejected_principal
        ")->first();

        $totalSubmittedSE  = (int)($m->total_submitted_se ?? 0);
        $pendingAdmin      = (int)($m->pending_admin ?? 0);
        $approvedAdmin     = (int)($m->approved_admin ?? 0);
        $rejectedAdmin     = (int)($m->rejected_admin ?? 0);
        $pendingSpv        = (int)($m->pending_spv ?? 0);
        $approvedSpv       = (int)($m->approved_spv ?? 0);
        $rejectedSpv       = (int)($m->rejected_spv ?? 0);
        $pendingPrincipal  = (int)($m->pending_principal ?? 0);
        $approvedPrincipal = (int)($m->approved_principal ?? 0);
        $rejectedPrincipal = (int)($m->rejected_principal ?? 0);

        // Chart 1: Perbandingan Submit SE vs Rejected Principal vs Approved Principal (Dapat Filter Tahun Khusus)
        $q1 = (clone $query);
        if ($c1Year) {
            $q1->whereYear(DB::raw("COALESCE(submitted_at, created_at)"), $c1Year);
        }
        $m1 = $q1->selectRaw("
            COUNT(*) as total_submitted_se,
            COUNT(CASE WHEN status IN ('APPROVED_EDP', 'INJECTED', 'EDP_APPROVED') THEN 1 END) as approved_principal,
            COUNT(CASE WHEN status IN ('EDP_REJECTED', 'REJECTED_EDP') THEN 1 END) as rejected_principal
        ")->first();

        $c1TotalSubmitted = (int)($m1->total_submitted_se ?? 0);
        $c1Approved       = (int)($m1->approved_principal ?? 0);
        $c1Rejected       = (int)($m1->rejected_principal ?? 0);

        $comparisonChart = [
            'total_submitted_se' => $c1TotalSubmitted,
            'approved_principal' => $c1Approved,
            'rejected_principal' => $c1Rejected,
        ];

        // 2. Direct SQL GroupBy for Chart 2: Top 10 Principal Area
        $q2 = (clone $query);
        if ($c2Year) {
            $q2->whereYear(DB::raw("COALESCE(submitted_at, created_at)"), $c2Year);
        }
        $top10PrincipalAreas = $q2
            ->selectRaw("
                REGEXP_REPLACE(COALESCE(region_code, 'OTHER'), '[0-9]+$', '') as area_code,
                COUNT(*) as total_submitted,
                COUNT(CASE WHEN status IN ('APPROVED_EDP', 'INJECTED', 'EDP_APPROVED') THEN 1 END) as approved_principal
            ")
            ->groupBy(DB::raw("REGEXP_REPLACE(COALESCE(region_code, 'OTHER'), '[0-9]+$', '')"))
            ->orderByDesc('total_submitted')
            ->limit(10)
            ->get();

        // 3. Direct SQL GroupBy for Chart 3: Sebaran Tipe Outlet
        $q3 = (clone $query);
        if ($c3Year) {
            $q3->whereYear(DB::raw("COALESCE(submitted_at, created_at)"), $c3Year);
        }
        $outletTypeDistribution = $q3
            ->selectRaw("
                COALESCE(type_outlet_desc, type_outlet_code, 'UNSPECIFIED') as outlet_type,
                COUNT(*) as total,
                COUNT(CASE WHEN status IN ('APPROVED_EDP', 'INJECTED', 'EDP_APPROVED') THEN 1 END) as approved
            ")
            ->groupBy(DB::raw("COALESCE(type_outlet_desc, type_outlet_code, 'UNSPECIFIED')"))
            ->orderByDesc('total')
            ->limit(8)
            ->get();

        // 4. Direct SQL GroupBy for Chart 4: Top 10 Cabang Submisi Terbanyak
        $q4 = (clone $query);
        if ($c4Year) {
            $q4->whereYear(DB::raw("COALESCE(submitted_at, created_at)"), $c4Year);
        }
        $top10Branches = $q4
            ->selectRaw("
                COALESCE(branch_name, branch_id, 'Cabang Unknown') as branch_name,
                MAX(region_code) as region_code,
                MAX(principal) as entity_name,
                COUNT(*) as total_submitted,
                COUNT(CASE WHEN status IN ('APPROVED_EDP', 'INJECTED', 'EDP_APPROVED') THEN 1 END) as approved_principal
            ")
            ->groupBy(DB::raw("COALESCE(branch_name, branch_id, 'Cabang Unknown')"))
            ->orderByDesc('total_submitted')
            ->limit(10)
            ->get();

        // 5. Recent Audit Logs (Top 5 Recent Activities - Fast Index Query)
        $auditLogs = [];
        try {
            $auditLogs = DB::table('audit_logs')
                ->orderBy('id', 'desc')
                ->limit(5)
                ->get();
        } catch (\Throwable $e) {
            $auditLogs = collect();
        }

        if (empty($auditLogs) || (is_object($auditLogs) && method_exists($auditLogs, 'isEmpty') && $auditLogs->isEmpty())) {
            // Fallback to recent submission activities using Primary Key Index (id DESC)
            $recentSubs = (clone $query)->select('id', 'updated_at', 'created_at', 'approved_by_edp', 'approved_by_spv', 'approved_by_admin', 'status', 'custcode_distributor', 'nama_noo', 'branch_name')
                ->orderBy('id', 'desc')
                ->limit(5)
                ->get();

            $auditLogs = $recentSubs->map(function ($s) {
                return (object)[
                    'id' => $s->id ?? rand(100, 999),
                    'timestamp' => $s->updated_at ?? $s->created_at ?? now()->toDateTimeString(),
                    'username' => $s->approved_by_edp ?? $s->approved_by_spv ?? $s->approved_by_admin ?? 'Sales Executive',
                    'role' => $s->status === 'APPROVED_EDP' ? 'EDP_PRINCIPAL' : ($s->status === 'APPROVED_SPV' ? 'SPV_AREA' : 'ADMIN_DISTRIBUTOR'),
                    'action' => $s->status ?? 'SUBMIT_NOO',
                    'table_name' => 'noo_submissions',
                    'row_key' => $s->custcode_distributor ?? (string)$s->id,
                    'notes' => "Aktivitas pengajuan toko '{$s->nama_noo}' (Cabang: {$s->branch_name}) status '{$s->status}'",
                ];
            });
        }

        // Available Years Dynamic Query
        $availableYears = DB::table('noo_submissions')
            ->selectRaw("DISTINCT EXTRACT(YEAR FROM COALESCE(submitted_at, created_at)) as yr")
            ->whereNotNull('created_at')
            ->pluck('yr')
            ->map(fn($y) => (int)$y)
            ->filter()
            ->sortDesc()
            ->values()
            ->toArray();

        if (empty($availableYears)) {
            $availableYears = [(int)date('Y')];
        }

        // Filter Options List (< 3ms)
        $regionsQuery = DB::table('master_branches')->select('region_code', 'region_name')->distinct()->whereNotNull('region_code');
        $entitiesQuery = DB::table('master_branches')->select('entity_code_principal', 'entity_name_principal', 'region_code')->distinct()->whereNotNull('entity_code_principal');
        $branchesQuery = DB::table('master_branches')->select('branch_id', 'branch_name', 'region_code', 'entity_code_principal');

        if ($userRole !== 'SUPERADMIN' && !empty($userRegion)) {
            $regionsQuery->where('region_code', 'LIKE', "{$userRegion}%");
            $entitiesQuery->where('region_code', 'LIKE', "{$userRegion}%");
            $branchesQuery->where('region_code', 'LIKE', "{$userRegion}%");
        }

        if ($userRole === 'ADMIN_PRINCIPAL' && !empty($userEntity)) {
            $entitiesQuery->where('entity_code_principal', $userEntity);
            $branchesQuery->where('entity_code_principal', $userEntity);
        }

        $regions = $regionsQuery->orderBy('region_code')->get();
        $entities = $entitiesQuery->orderBy('entity_code_principal')->get();
        $branches = $branchesQuery->orderBy('branch_id', 'asc')->get();

        return Inertia::render('Edp/Dashboard', [
            'metrics' => [
                'total_submitted_se' => $totalSubmittedSE,
                'pending_admin' => $pendingAdmin,
                'approved_admin' => $approvedAdmin,
                'rejected_admin' => $rejectedAdmin,
                'pending_spv' => $pendingSpv,
                'approved_spv' => $approvedSpv,
                'rejected_spv' => $rejectedSpv,
                'pending_principal' => $pendingPrincipal,
                'approved_principal' => $approvedPrincipal,
                'rejected_principal' => $rejectedPrincipal,
            ],
            'charts' => [
                'comparison' => $comparisonChart,
                'top_principal_areas' => $top10PrincipalAreas,
                'outlet_types' => $outletTypeDistribution,
                'top_branches' => $top10Branches,
            ],
            'recentLogs' => $auditLogs,
            'filters' => [
                'region_code' => $request->input('region_code', ''),
                'principal' => $request->input('principal', ''),
                'branch_id' => $request->input('branch_id', ''),
                'year' => $globalYear ?? '',
                'chart1_year' => $c1Year ?? '',
                'chart2_year' => $c2Year ?? '',
                'chart3_year' => $c3Year ?? '',
                'chart4_year' => $c4Year ?? '',
            ],
            'filterOptions' => [
                'regions' => $regions,
                'entities' => $entities,
                'branches' => $branches,
                'years' => $availableYears,
            ],
            'userRole' => $userRole,
        ]);
    }
}
