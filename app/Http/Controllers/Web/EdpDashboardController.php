<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
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
        $globalMonth = $request->input('month');

        if ($request->filled('year')) {
            $query->whereYear(DB::raw("COALESCE(submitted_at, created_at)"), $globalYear);
        }
        if ($request->filled('months')) {
            $rawMonths = explode(',', (string) $request->input('months'));
            $monthsArray = array_values(array_filter(array_map('intval', $rawMonths)));
            if (!empty($monthsArray)) {
                $query->whereRaw("EXTRACT(MONTH FROM COALESCE(submitted_at, created_at)) IN (" . implode(',', $monthsArray) . ")");
            }
        } elseif ($request->filled('month')) {
            $query->whereMonth(DB::raw("COALESCE(submitted_at, created_at)"), (int)$globalMonth);
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

        // 5. Monitoring Target RO Salesman (P2 = Target 150 RO/bulan, P4 = Target 300 RO/bulan)
        $salesmenQuery = DB::table('master_salesmen')
            ->leftJoin('master_branches', 'master_salesmen.branch_id', '=', 'master_branches.branch_id')
            ->select(
                'master_salesmen.salesman_code',
                'master_salesmen.salesman_name',
                'master_salesmen.branch_id',
                DB::raw("COALESCE(master_branches.branch_name, master_salesmen.branch_id) as branch_name"),
                DB::raw("COALESCE(master_branches.region_code, master_salesmen.region_code) as region_code"),
                DB::raw("COALESCE(master_branches.entity_code_principal, master_salesmen.entity_code_principal) as entity_code_principal")
            )
            ->where('master_salesmen.is_active', true);

        if ($userRole !== 'SUPERADMIN' && !empty($userRegion)) {
            $salesmenQuery->where(function ($q) use ($userRegion) {
                $q->where('master_branches.region_code', 'LIKE', "{$userRegion}%")
                  ->orWhere('master_salesmen.region_code', 'LIKE', "{$userRegion}%");
            });
        }
        if ($userRole === 'ADMIN_PRINCIPAL' && !empty($userEntity)) {
            $salesmenQuery->where(function ($q) use ($userEntity) {
                $q->where('master_branches.entity_code_principal', $userEntity)
                  ->orWhere('master_salesmen.entity_code_principal', $userEntity);
            });
        }

        if ($request->filled('region_code')) {
            $rCode = $request->input('region_code');
            $salesmenQuery->where(function ($q) use ($rCode) {
                $q->where('master_branches.region_code', $rCode)
                  ->orWhere('master_salesmen.region_code', $rCode);
            });
        }
        if ($request->filled('principal')) {
            $p = $request->input('principal');
            $salesmenQuery->where(function ($q) use ($p) {
                $q->where('master_branches.entity_code_principal', $p)
                  ->orWhere('master_salesmen.entity_code_principal', $p);
            });
        }
        if ($request->filled('branch_id')) {
            $salesmenQuery->where('master_salesmen.branch_id', $request->input('branch_id'));
        }

        $salesmenList = $salesmenQuery->orderBy('master_salesmen.salesman_code', 'asc')->get();

        $subStatsQuery = DB::table('noo_submissions');
        if ($request->filled('year')) {
            $subStatsQuery->whereYear(DB::raw("COALESCE(submitted_at, created_at)"), $globalYear);
        }
        if ($request->filled('month')) {
            $subStatsQuery->whereMonth(DB::raw("COALESCE(submitted_at, created_at)"), (int)$globalMonth);
        }

        $salesmanStats = $subStatsQuery
            ->selectRaw("
                salesman_code,
                COUNT(CASE WHEN status IN ('APPROVED_EDP', 'INJECTED', 'EDP_APPROVED') THEN 1 END) as approved_ro_count,
                MAX(
                    CASE WHEN m1 IN ('Y', 'YES') THEN 1 ELSE 0 END +
                    CASE WHEN m2 IN ('Y', 'YES') THEN 1 ELSE 0 END +
                    CASE WHEN m3 IN ('Y', 'YES') THEN 1 ELSE 0 END +
                    CASE WHEN m4 IN ('Y', 'YES') THEN 1 ELSE 0 END
                ) as max_active_weeks
            ")
            ->groupBy('salesman_code')
            ->get()
            ->keyBy('salesman_code');

        $salesmanTargetsData = $salesmenList->map(function ($s) use ($salesmanStats) {
            $code = $s->salesman_code;
            $stat = $salesmanStats->get($code);

            $approvedRo = (int)($stat->approved_ro_count ?? 0);
            $maxWeeks = (int)($stat->max_active_weeks ?? 0);

            $visitType = ($maxWeeks >= 4) ? 'P4' : 'P2';
            $targetRo = ($visitType === 'P4') ? 300 : 150;

            $percentage = $targetRo > 0 ? round(($approvedRo / $targetRo) * 100, 1) : 0;
            $isAchieved = $approvedRo >= $targetRo;

            return [
                'salesman_code' => $code,
                'salesman_name' => $s->salesman_name ?: $code,
                'branch_id' => $s->branch_id,
                'branch_name' => $s->branch_name ?: $s->branch_id,
                'region_code' => $s->region_code,
                'entity_code_principal' => $s->entity_code_principal,
                'visit_type' => $visitType,
                'approved_ro' => $approvedRo,
                'target_ro' => $targetRo,
                'percentage' => $percentage,
                'is_achieved' => $isAchieved,
            ];
        })->values();

        // 6. Recent Audit Logs (Top 5 Recent Activities - Fast Index Query)
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
            $entitiesQuery->where(function ($q) use ($userEntity) {
                $q->where('entity_code_principal', $userEntity)
                  ->orWhere('entity_name_principal', 'ILIKE', "%{$userEntity}%")
                  ->orWhereRaw("? ILIKE '%' || entity_code_principal || '%'", [$userEntity]);
            });
            $branchesQuery->where(function ($q) use ($userEntity) {
                $q->where('entity_code_principal', $userEntity)
                  ->orWhereRaw("? ILIKE '%' || entity_code_principal || '%'", [$userEntity]);
            });
        }

        $regions = $regionsQuery->orderBy('region_code')->get();
        $entities = $entitiesQuery->orderBy('entity_code_principal')->get();
        $branches = $branchesQuery->orderBy('branch_id', 'asc')->get();

        if ($entities->isEmpty()) {
            $fallbackEntities = DB::table('master_branches')
                ->select('entity_code_principal', 'entity_name_principal', 'region_code')
                ->distinct()
                ->whereNotNull('entity_code_principal');
            if ($userRole !== 'SUPERADMIN' && !empty($userRegion)) {
                $fallbackEntities->where('region_code', 'LIKE', "{$userRegion}%");
            }
            $entities = $fallbackEntities->orderBy('entity_code_principal')->get();
        }

        if ($branches->isEmpty()) {
            $fallbackBranches = DB::table('master_branches')
                ->select('branch_id', 'branch_name', 'region_code', 'entity_code_principal');
            if ($userRole !== 'SUPERADMIN' && !empty($userRegion)) {
                $fallbackBranches->where('region_code', 'LIKE', "{$userRegion}%");
            }
            $branches = $fallbackBranches->orderBy('branch_id', 'asc')->get();
        }

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
                'salesman_targets' => $salesmanTargetsData,
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
                'month' => $globalMonth ?? '',
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

    /**
     * Helper untuk memastikan tabel target_ros ada di database PostgreSQL.
     */
    private function ensureTargetRosTableExists(): void
    {
        if (!Schema::hasTable('target_ros')) {
            Schema::create('target_ros', function (Blueprint $table) {
                $table->id();
                $table->integer('period_year');
                $table->integer('period_month');
                $table->string('branch_id', 50);
                $table->string('salesman_code', 50);
                $table->string('visit_type', 10)->default('F2');
                $table->integer('target_ro')->default(0);
                $table->string('region_code', 50)->nullable();
                $table->unsignedBigInteger('uploaded_by')->nullable();
                $table->timestamps();

                $table->unique(['period_year', 'period_month', 'branch_id', 'salesman_code'], 'target_ros_unique_period_branch_salesman');
                $table->index(['period_year', 'period_month']);
                $table->index(['branch_id', 'salesman_code']);
            });
        }
    }

    /**
     * Download Template Excel Target RO yang Rapi & User-Friendly.
     */
    public function downloadTargetRoTemplate()
    {
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Template Target RO');

        // Font default
        $spreadsheet->getDefaultStyle()->getFont()->setName('Calibri')->setSize(10);

        // Header Names rapi
        $headers = [
            'A1' => 'Kode Cabang (branch_id)',
            'B1' => 'Kode Salesman (salesman_code)',
            'C1' => 'Tipe Kunjungan (F2 / F4)',
            'D1' => 'Target RO Bulanan',
        ];

        foreach ($headers as $cell => $text) {
            $sheet->setCellValue($cell, $text);
        }

        // Header Styling
        $headerStyle = [
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
                'size' => 11,
            ],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['rgb' => '1E3A8A'], // Royal Navy Blue
            ],
            'alignment' => [
                'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                    'color' => ['rgb' => '0F172A'],
                ],
            ],
        ];
        $sheet->getStyle('A1:D1')->applyFromArray($headerStyle);
        $sheet->getRowDimension(1)->setRowHeight(30);

        // Sample Rows
        $sampleData = [
            ['A01', 'SLS-001', 'F4', 300],
            ['A01', 'SLS-002', 'F2', 150],
            ['B02', 'SLS-003', 'F4', 300],
            ['B02', 'SLS-004', 'F2', 150],
        ];

        $rowNum = 2;
        foreach ($sampleData as $row) {
            $sheet->setCellValue("A{$rowNum}", $row[0]);
            $sheet->setCellValue("B{$rowNum}", $row[1]);
            $sheet->setCellValue("C{$rowNum}", $row[2]);
            $sheet->setCellValue("D{$rowNum}", $row[3]);

            $sheet->getStyle("A{$rowNum}:C{$rowNum}")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("D{$rowNum}")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT);
            $sheet->getStyle("A{$rowNum}:D{$rowNum}")->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN)->getColor()->setRGB('CBD5E1');
            $rowNum++;
        }

        // Instructions note below sample rows
        $sheet->setCellValue("A7", "* Petunjuk Pengisian Target RO Bulanan:");
        $sheet->setCellValue("A8", "1. 'Kode Cabang (branch_id)' : Diisi kode branch resmi distributor (contoh: A01, B02).");
        $sheet->setCellValue("A9", "2. 'Kode Salesman (salesman_code)' : Diisi kode unik salesman (contoh: SLS-001).");
        $sheet->setCellValue("A10", "3. 'Tipe Kunjungan (F2 / F4)' : Diisi F2 (dua mingguan) atau F4 (mingguan).");
        $sheet->setCellValue("A11", "4. 'Target RO Bulanan' : Diisi angka jumlah kuota target RO di bulan tersebut.");

        $sheet->getStyle('A7:A11')->getFont()->setItalic(true)->setSize(9)->getColor()->setRGB('475569');

        // Auto width columns
        foreach (range('A', 'D') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $fileName = "Template_Target_RO_Bulanan.xlsx";
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);

        return response()->stream(function () use ($writer) {
            $writer->save('php://output');
        }, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => "attachment; filename=\"{$fileName}\"",
            'Cache-Control' => 'max-age=0',
        ]);
    }

    /**
     * Fitur Upload Berkas Target RO oleh EDP per Bulan & Tahun.
     */
    public function uploadTargetRo(Request $request): RedirectResponse
    {
        $this->ensureTargetRosTableExists();

        $request->validate([
            'year' => 'required|integer|min:2020|max:2050',
            'month' => 'required|integer|min:1|max:12',
            'file' => 'required|file|mimes:xls,xlsx,csv,txt|max:10240',
        ]);

        try {
            $user = Auth::user();
            $userRole = $user->role ?? 'EDP_REGION';
            $userRegion = $user->region_code ?? null;

            $year = (int)$request->input('year');
            $month = (int)$request->input('month');
            $file = $request->file('file');

            // Scoped Branch IDs for EDP Region
            $allowedBranchIds = [];
            if ($userRole !== 'SUPERADMIN' && !empty($userRegion)) {
                $allowedBranchIds = DB::table('master_branches')
                    ->where('region_code', 'LIKE', "{$userRegion}%")
                    ->pluck('branch_id')
                    ->toArray();
            }

            // Build map of known valid salesmen in database per branch
            $knownMaster = DB::table('master_salesmen')->select('branch_id', 'salesman_code')->get();
            $knownSubmissions = DB::table('noo_submissions')->select('branch_id', 'salesman_code')->whereNotNull('salesman_code')->where('salesman_code', '!=', '')->get();

            $knownSalesmenMap = [];
            foreach ($knownMaster as $km) {
                $b = trim((string)$km->branch_id);
                $c = trim((string)$km->salesman_code);
                $knownSalesmenMap["{$b}:{$c}"] = true;
            }
            foreach ($knownSubmissions as $ks) {
                $b = trim((string)$ks->branch_id);
                $c = trim((string)$ks->salesman_code);
                $knownSalesmenMap["{$b}:{$c}"] = true;
            }

            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file->getRealPath());
            $sheet = $spreadsheet->getActiveSheet();
            $rows = $sheet->toArray();

            if (empty($rows) || count($rows) < 2) {
                return back()->with('error', 'Berkas Excel kosong atau tidak memiliki baris data.');
            }

            // Detect column indices from header (Row 0)
            $header = array_map(fn($val) => strtolower(trim((string)$val)), $rows[0]);

            $colBranch = 0;
            $colSalesman = 1;
            $colVisit = 2;
            $colTarget = 3;

            foreach ($header as $idx => $hText) {
                if (str_contains($hText, 'branch') || str_contains($hText, 'cabang')) {
                    $colBranch = $idx;
                } elseif (str_contains($hText, 'sales') || str_contains($hText, 'salesman')) {
                    $colSalesman = $idx;
                } elseif (str_contains($hText, 'visit') || str_contains($hText, 'f2') || str_contains($hText, 'tipe') || str_contains($hText, 'kunjungan')) {
                    $colVisit = $idx;
                } elseif (str_contains($hText, 'target') || str_contains($hText, 'ro')) {
                    $colTarget = $idx;
                }
            }

            $savedCount = 0;
            $skippedCount = 0;
            $unmatchedSalesmen = [];

            for ($i = 1; $i < count($rows); $i++) {
                $r = $rows[$i];
                $branchId = trim((string)($r[$colBranch] ?? ''));
                $salesmanCode = trim((string)($r[$colSalesman] ?? ''));
                $rawVisit = strtoupper(trim((string)($r[$colVisit] ?? '')));
                $rawTarget = trim((string)($r[$colTarget] ?? ''));

                if (empty($branchId) || empty($salesmanCode)) {
                    continue; // Skip empty rows
                }

                // Scope validation
                if (!empty($allowedBranchIds) && !in_array($branchId, $allowedBranchIds, true)) {
                    $skippedCount++;
                    continue;
                }

                // Check exact match in database
                if (!isset($knownSalesmenMap["{$branchId}:{$salesmanCode}"])) {
                    $unmatchedSalesmen[] = "Kode '{$salesmanCode}' di Cabang {$branchId}";
                }

                $visitType = str_contains($rawVisit, '4') ? 'F4' : 'F2';
                $targetRo = is_numeric($rawTarget) ? (int)$rawTarget : ($visitType === 'F4' ? 300 : 150);

                DB::table('target_ros')->updateOrInsert(
                    [
                        'period_year' => $year,
                        'period_month' => $month,
                        'branch_id' => $branchId,
                        'salesman_code' => $salesmanCode,
                    ],
                    [
                        'visit_type' => $visitType,
                        'target_ro' => $targetRo,
                        'region_code' => $userRegion,
                        'uploaded_by' => $user->id ?? null,
                        'updated_at' => now(),
                        'created_at' => now(),
                    ]
                );

                $savedCount++;
            }

            $msg = "Target RO bulan {$month}/{$year} berhasil di-upload ({$savedCount} salesman tersimpan).";
            if (!empty($unmatchedSalesmen)) {
                $uniqueUnmatched = array_values(array_unique($unmatchedSalesmen));
                $sampleList = implode(', ', array_slice($uniqueUnmatched, 0, 5));
                $msg .= " ⚠️ PERINGATAN: Terdapat " . count($uniqueUnmatched) . " kode salesman yang tidak cocok dengan master database: [{$sampleList}]. Harap pastikan penulisan kode salesman pada file Excel sama persis dengan master data.";
                return back()->with('warning', $msg);
            }

            if ($skippedCount > 0) {
                $msg .= " ({$skippedCount} baris dilewati karena di luar wilayah hak akses)";
            }

            return back()->with('success', $msg);
        } catch (\Throwable $e) {
            return back()->with('error', "Gagal upload target RO: {$e->getMessage()}");
        }
    }

    /**
     * Halaman Khusus Monitoring Target RO vs Realisasi Approved Salesman.
     * Realisasi NOO berdasarkan status APPROVED pada edp_decision & terpisah per bulan sesuai submitted_at.
     */
    public function monitoringRo(Request $request): Response
    {
        $this->ensureTargetRosTableExists();

        $user = Auth::user();
        $userRole = $user->role ?? 'EDP_REGION';
        $userRegion = $user->region_code ?? null;
        $userEntity = $user->entity_code_principal ?? null;

        // Default to current year & month if empty
        $globalYear = $request->filled('year') ? (int)$request->input('year') : (int)date('Y');
        $globalMonth = $request->filled('month') ? (int)$request->input('month') : (int)date('n');

        // 1. Query Uploaded Target RO for the selected month & year (strict exact salesman_code matching)
        $uploadedTargetsRaw = DB::table('target_ros')
            ->where('period_year', $globalYear)
            ->where('period_month', $globalMonth)
            ->get();

        $uploadedTargets = [];
        $uploadedBranchIds = [];
        foreach ($uploadedTargetsRaw as $tObj) {
            $uploadedBranchIds[] = $tObj->branch_id;
            $rawCode = strtoupper(trim((string)$tObj->salesman_code));
            $uploadedTargets[$rawCode] = $tObj;
        }
        $uploadedBranchIds = array_values(array_unique($uploadedBranchIds));
        $hasAnyTargetUploaded = count($uploadedTargets) > 0;

        // 2. Fetch Active Branches Only (is_active = 1 and branch_name NOT LIKE '%(NON ACTIVE)%')
        $masterBranches = DB::table('master_branches')
            ->select(
                'branch_id',
                'branch_name',
                'region_code',
                'region_name',
                'entity_code_principal',
                'entity_name_principal'
            )
            ->where('is_active', 1)
            ->where(function ($q) {
                $q->whereNull('branch_name')
                  ->orWhere('branch_name', 'NOT LIKE', '%(NON ACTIVE)%');
            })
            ->get();

        $submissionBranches = DB::table('noo_submissions')
            ->select(
                'branch_id',
                DB::raw("MAX(branch_name) as branch_name"),
                DB::raw("MAX(region_code) as region_code"),
                DB::raw("MAX(principal) as entity_name_principal")
            )
            ->whereNotNull('branch_id')
            ->where('branch_id', '!=', '')
            ->where(function ($q) {
                $q->whereNull('branch_name')
                  ->orWhere('branch_name', 'NOT LIKE', '%(NON ACTIVE)%');
            })
            ->groupBy('branch_id')
            ->get();

        $branchesMap = [];
        foreach ($masterBranches as $mb) {
            $branchesMap[$mb->branch_id] = [
                'branch_id' => $mb->branch_id,
                'branch_name' => $mb->branch_name ?: $mb->branch_id,
                'region_code' => $mb->region_code ?: 'REGIONAL',
                'region_name' => $mb->region_name ?: $mb->region_code,
                'entity_code_principal' => $mb->entity_code_principal ?: 'ASW',
                'entity_name_principal' => $mb->entity_name_principal ?: 'ASWFOODS',
            ];
        }
        foreach ($submissionBranches as $sb) {
            if (!isset($branchesMap[$sb->branch_id])) {
                $branchesMap[$sb->branch_id] = [
                    'branch_id' => $sb->branch_id,
                    'branch_name' => $sb->branch_name ?: $sb->branch_id,
                    'region_code' => $sb->region_code ?: 'REGIONAL',
                    'region_name' => $sb->region_code ?: 'REGIONAL',
                    'entity_code_principal' => 'ASW',
                    'entity_name_principal' => $sb->entity_name_principal ?: 'ASWFOODS',
                ];
            }
        }

        // Apply Data Isolation / Filtering on Branches Map
        if ($userRole !== 'SUPERADMIN' && !empty($userRegion)) {
            $branchesMap = array_filter($branchesMap, function ($b) use ($userRegion) {
                return (string)$b['region_code'] === $userRegion || str_starts_with((string)$b['region_code'], $userRegion);
            });
        }

        if ($request->filled('region_code')) {
            $rCode = $request->input('region_code');
            $branchesMap = array_filter($branchesMap, function ($b) use ($rCode) {
                return $b['region_code'] === $rCode;
            });
        }
        if ($request->filled('principal')) {
            $p = $request->input('principal');
            $branchesMap = array_filter($branchesMap, function ($b) use ($p) {
                return str_contains(strtolower((string)$b['entity_name_principal']), strtolower($p)) ||
                       $b['entity_code_principal'] === $p;
            });
        }
        if ($request->filled('branch_id')) {
            $bId = $request->input('branch_id');
            $branchesMap = array_filter($branchesMap, function ($b) use ($bId) {
                return $b['branch_id'] === $bId;
            });
        }

        // Sort branches by branch_id
        usort($branchesMap, function ($a, $b) {
            return strcmp((string)$a['branch_id'], (string)$b['branch_id']);
        });

        // Track missing target branches for warning alert
        $missingTargetBranches = [];
        foreach ($branchesMap as $bItem) {
            if (!in_array($bItem['branch_id'], $uploadedBranchIds, true)) {
                $missingTargetBranches[] = [
                    'branch_id' => $bItem['branch_id'],
                    'branch_name' => $bItem['branch_name'],
                ];
            }
        }

        // 3. Query Salesmen (Combination from master_salesmen and noo_submissions)
        $salesmenFromMaster = DB::table('master_salesmen')
            ->select('salesman_code', 'salesman_name', 'branch_id', 'region_code')
            ->whereNotNull('salesman_code')
            ->get();

        $salesmenFromSubmissions = DB::table('noo_submissions')
            ->select(
                'salesman_code',
                DB::raw("MAX(salesman_name) as salesman_name"),
                'branch_id',
                DB::raw("MAX(region_code) as region_code")
            )
            ->whereNotNull('salesman_code')
            ->where('salesman_code', '!=', '')
            ->groupBy('salesman_code', 'branch_id')
            ->get();

        $salesmenMap = [];
        foreach ($salesmenFromMaster as $s) {
            $salesmenMap[$s->salesman_code] = [
                'salesman_code' => $s->salesman_code,
                'salesman_name' => $s->salesman_name ?: $s->salesman_code,
                'branch_id' => $s->branch_id,
                'region_code' => $s->region_code,
            ];
        }
        foreach ($salesmenFromSubmissions as $s) {
            if (!isset($salesmenMap[$s->salesman_code])) {
                $salesmenMap[$s->salesman_code] = [
                    'salesman_code' => $s->salesman_code,
                    'salesman_name' => $s->salesman_name ?: $s->salesman_code,
                    'branch_id' => $s->branch_id,
                    'region_code' => $s->region_code,
                ];
            }
        }

        // 4. Query Realisasi NOO Approved based on edp_decision = 'APPROVED' & grouped per month by submitted_at
        $subStatsQuery = DB::table('noo_submissions')
            ->selectRaw("
                salesman_code,
                branch_id,
                EXTRACT(MONTH FROM COALESCE(submitted_at, created_at)) as sub_month,
                EXTRACT(YEAR FROM COALESCE(submitted_at, created_at)) as sub_year,
                COUNT(CASE WHEN (edp_decision = 'APPROVED' OR status IN ('APPROVED_EDP', 'INJECTED', 'EDP_APPROVED')) AND COALESCE(is_ro, true) = true THEN 1 END) as approved_ro_count,
                MAX(
                    CASE WHEN m1 IN ('Y', 'YES') THEN 1 ELSE 0 END +
                    CASE WHEN m2 IN ('Y', 'YES') THEN 1 ELSE 0 END +
                    CASE WHEN m3 IN ('Y', 'YES') THEN 1 ELSE 0 END +
                    CASE WHEN m4 IN ('Y', 'YES') THEN 1 ELSE 0 END
                ) as max_active_weeks
            ")
            ->whereNotNull('salesman_code')
            ->groupBy('salesman_code', 'branch_id', 'sub_month', 'sub_year')
            ->get();

        $statsLookupBySalesman = [];
        foreach ($subStatsQuery as $st) {
            $code = $st->salesman_code;
            if (!isset($statsLookupBySalesman[$code])) {
                $statsLookupBySalesman[$code] = [];
            }
            $statsLookupBySalesman[$code][] = [
                'month' => (int)$st->sub_month,
                'year' => (int)$st->sub_year,
                'approved_count' => (int)$st->approved_ro_count,
                'max_weeks' => (int)$st->max_active_weeks,
            ];
        }

        // 5. Group Salesmen under each Branch
        $totalSalesmenGlobal = 0;
        $totalAchievedGlobal = 0;
        $totalApprovedRoGlobal = 0;
        $totalTargetRoGlobal = 0;

        $branchesData = array_map(function ($b) use ($salesmenMap, $statsLookupBySalesman, $uploadedTargets, $globalMonth, $globalYear, &$totalSalesmenGlobal, &$totalAchievedGlobal, &$totalApprovedRoGlobal, &$totalTargetRoGlobal) {
            $branchId = $b['branch_id'];

            $branchSalesmen = array_filter($salesmenMap, function ($s) use ($branchId) {
                return $s['branch_id'] === $branchId;
            });

            $salesmenItems = [];
            $branchApprovedRo = 0;
            $branchTargetRo = 0;
            $branchAchievedCount = 0;

            foreach ($branchSalesmen as $s) {
                $code = $s['salesman_code'];
                $monthlyList = $statsLookupBySalesman[$code] ?? [];

                // Filter realization for the selected month and year based on submitted_at
                $approvedRo = 0;
                $maxWeeks = 0;
                foreach ($monthlyList as $mItem) {
                    if ($mItem['month'] === $globalMonth && $mItem['year'] === $globalYear) {
                        $approvedRo += $mItem['approved_count'];
                    }
                    if ($mItem['max_weeks'] > $maxWeeks) {
                        $maxWeeks = $mItem['max_weeks'];
                    }
                }

                // Check if target was uploaded by EDP for this salesman (exact salesman_code matching)
                $codeRaw = strtoupper(trim((string)$code));
                $tObj = $uploadedTargets[$codeRaw] ?? null;

                if ($tObj !== null) {
                    $targetRo = (int)$tObj->target_ro;
                    $visitType = strtoupper(trim((string)$tObj->visit_type));
                    $isCustomTarget = true;
                } else {
                    $visitType = null; // Target belum di-upload oleh EDP!
                    $targetRo = 0;     // Target belum di-upload oleh EDP!
                    $isCustomTarget = false;
                }

                $percentage = $targetRo > 0 ? round(($approvedRo / $targetRo) * 100, 1) : 0;
                $isAchieved = $targetRo > 0 && $approvedRo >= $targetRo;

                if ($isAchieved) {
                    $branchAchievedCount++;
                    $totalAchievedGlobal++;
                }

                $branchApprovedRo += $approvedRo;
                $branchTargetRo += $targetRo;
                $totalApprovedRoGlobal += $approvedRo;
                $totalTargetRoGlobal += $targetRo;
                $totalSalesmenGlobal++;

                $salesmenItems[] = [
                    'salesman_code' => $code,
                    'salesman_name' => $s['salesman_name'],
                    'visit_type' => $visitType,
                    'approved_ro' => $approvedRo,
                    'target_ro' => $targetRo,
                    'percentage' => $percentage,
                    'is_achieved' => $isAchieved,
                    'is_custom_target' => $isCustomTarget,
                    'monthly_stats' => $monthlyList,
                ];
            }

            $branchPct = $branchTargetRo > 0 ? round(($branchApprovedRo / $branchTargetRo) * 100, 1) : 0;
            $isBranchAchieved = $branchTargetRo > 0 && $branchApprovedRo >= $branchTargetRo;

            return [
                'branch_id' => $b['branch_id'],
                'branch_name' => $b['branch_name'],
                'region_code' => $b['region_code'],
                'region_name' => $b['region_name'],
                'entity_code_principal' => $b['entity_code_principal'],
                'entity_name_principal' => $b['entity_name_principal'],
                'total_approved_ro' => $branchApprovedRo,
                'total_target_ro' => $branchTargetRo,
                'branch_percentage' => $branchPct,
                'is_branch_achieved' => $isBranchAchieved,
                'achieved_salesmen_count' => $branchAchievedCount,
                'total_salesmen_count' => count($salesmenItems),
                'salesmen' => array_values($salesmenItems),
            ];
        }, $branchesMap);

        // Available Years Query
        $availableYears = DB::table('noo_submissions')
            ->selectRaw("DISTINCT EXTRACT(YEAR FROM COALESCE(submitted_at, created_at)) as yr")
            ->whereNotNull('created_at')
            ->pluck('yr')
            ->map(fn($y) => (int)$y)
            ->filter()
            ->sortDesc()
            ->values()
            ->toArray();

        if (!in_array((int)date('Y'), $availableYears, true)) {
            $availableYears[] = (int)date('Y');
            rsort($availableYears);
        }

        // Filter Options List (Active Only)
        $regionsQuery = DB::table('master_branches')->select('region_code', 'region_name')->distinct()->where('is_active', 1)->whereNotNull('region_code');
        $entitiesQuery = DB::table('master_branches')->select('entity_code_principal', 'entity_name_principal', 'region_code')->distinct()->where('is_active', 1)->whereNotNull('entity_code_principal');
        $branchesFilterQuery = DB::table('master_branches')
            ->select('branch_id', 'branch_name', 'region_code', 'entity_code_principal')
            ->where('is_active', 1)
            ->where(function ($q) {
                $q->whereNull('branch_name')
                  ->orWhere('branch_name', 'NOT LIKE', '%(NON ACTIVE)%');
            });

        if ($userRole !== 'SUPERADMIN' && !empty($userRegion)) {
            $regionsQuery->where('region_code', 'LIKE', "{$userRegion}%");
            $entitiesQuery->where('region_code', 'LIKE', "{$userRegion}%");
            $branchesFilterQuery->where('region_code', 'LIKE', "{$userRegion}%");
        }

        $regions = $regionsQuery->orderBy('region_code')->get();
        $entities = $entitiesQuery->orderBy('entity_code_principal')->get();
        $branchesOptions = $branchesFilterQuery->orderBy('branch_id', 'asc')->get();

        return Inertia::render('Edp/MonitoringRo', [
            'branchesData' => array_values($branchesData),
            'summary' => [
                'total_branches' => count($branchesData),
                'total_salesmen' => $totalSalesmenGlobal,
                'total_achieved' => $totalAchievedGlobal,
                'total_approved_ro' => $totalApprovedRoGlobal,
                'total_target_ro' => $totalTargetRoGlobal,
            ],
            'missingTargetBranches' => array_values($missingTargetBranches),
            'hasTargetUploaded' => $hasAnyTargetUploaded,
            'filters' => [
                'region_code' => $request->input('region_code', ''),
                'principal' => $request->input('principal', ''),
                'branch_id' => $request->input('branch_id', ''),
                'month' => (string)$globalMonth,
                'year' => (string)$globalYear,
            ],
            'filterOptions' => [
                'regions' => $regions,
                'entities' => $entities,
                'branches' => $branchesOptions,
                'years' => array_values($availableYears),
            ],
            'userRole' => $userRole,
        ]);
    }
}
