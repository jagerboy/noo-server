<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class EdpLogsController extends Controller
{
    public function index(Request $request): Response
    {
        $user = Auth::user();
        if (($user->role ?? 'EDP_REGION') === 'EDP_REGION') {
            abort(403, 'Akses Ditolak. Halaman Logs & Audit hanya dapat diakses oleh Superadmin dan Admin Principal.');
        }

        $query = DB::table('activity_logs');

        if ($request->filled('search')) {
            $s = $request->input('search');
            $query->where(function ($q) use ($s) {
                $q->where('username', 'ILIKE', "%{$s}%")
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

        return Inertia::render('Edp/Logs', [
            'logs' => $logs,
            'filters' => $request->only(['search']),
        ]);
    }
}
