<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

use App\Services\ExcelExportService;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\Http\RedirectResponse;

class EdpLogsController extends Controller
{
    public function __construct(
        protected ExcelExportService $excelExportService
    ) {}

    public function index(Request $request): Response
    {
        $user = Auth::user();
        if (($user->role ?? 'EDP_REGION') === 'EDP_REGION') {
            abort(403, 'Akses Ditolak. Halaman Logs & Audit hanya dapat diakses oleh Superadmin dan Admin Principal.');
        }

        $query = DB::table('activity_logs');

        if ($request->filled('role') && $request->input('role') !== 'ALL') {
            $query->where('user_role', $request->input('role'));
        }

        if ($request->filled('search')) {
            $s = $request->input('search');
            $query->where(function ($q) use ($s) {
                $q->where('username', 'ILIKE', "%{$s}%")
                  ->orWhere('user_role', 'ILIKE', "%{$s}%")
                  ->orWhere('action', 'ILIKE', "%{$s}%")
                  ->orWhere('module', 'ILIKE', "%{$s}%")
                  ->orWhere('description', 'ILIKE', "%{$s}%");
            });
        }

        $perPage = (int) $request->input('per_page', 10);
        if ($perPage <= 0) {
            $perPage = 100000;
        }

        $logs = $query->orderBy('id', 'desc')->paginate($perPage)->withQueryString();

        $defaultRoles = ['SUPERADMIN', 'ADMIN_PRINCIPAL', 'EDP_REGION', 'SPV_AREA', 'ADMIN_DISTRIBUTOR'];

        $dbRoles = DB::table('activity_logs')
            ->whereNotNull('user_role')
            ->where('user_role', '!=', '')
            ->distinct()
            ->pluck('user_role')
            ->toArray();

        $allRoles = array_values(array_unique(array_merge($defaultRoles, $dbRoles)));
        sort($allRoles);

        return Inertia::render('Edp/Logs', [
            'logs' => $logs,
            'filters' => $request->only(['search', 'role']),
            'availableRoles' => $allRoles,
        ]);
    }

    public function exportExcel(Request $request): StreamedResponse|RedirectResponse
    {
        $user = Auth::user();
        if (($user->role ?? 'EDP_REGION') === 'EDP_REGION') {
            abort(403, 'Akses Ditolak.');
        }

        $query = DB::table('activity_logs');

        $filterRole = $request->input('role', 'ALL');
        if (!empty($filterRole) && $filterRole !== 'ALL') {
            $query->where('user_role', $filterRole);
        }

        $search = $request->input('search', '');
        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('username', 'ILIKE', "%{$search}%")
                  ->orWhere('user_role', 'ILIKE', "%{$search}%")
                  ->orWhere('action', 'ILIKE', "%{$search}%")
                  ->orWhere('module', 'ILIKE', "%{$search}%")
                  ->orWhere('description', 'ILIKE', "%{$search}%");
            });
        }

        $logs = $query->orderBy('id', 'desc')->get()->toArray();

        $excelBinary = $this->excelExportService->generateActivityLogsExcel($logs, (string)$filterRole, (string)$search);

        $filename = "AUDIT_ACTIVITY_LOGS_" . date('Ymd_His') . ".xlsx";

        return response()->stream(
            function () use ($excelBinary) {
                echo $excelBinary;
            },
            200,
            [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Content-Disposition' => "attachment; filename=\"{$filename}\"",
                'Cache-Control' => 'max-age=0',
            ]
        );
    }
}
