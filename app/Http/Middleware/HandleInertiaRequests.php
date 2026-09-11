<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $authUser = null;

        if ($request->is('spv*')) {
            $spvUser = session('spv_user') ?? $request->user();
            if ($spvUser) {
                $branchId = $spvUser->branch_id ?? null;
                $salescode = $spvUser->salesman_code ?? $spvUser->salescode ?? null;
                $spvName = $spvUser->name ?? $spvUser->nama ?? null;

                $branchIds = [];
                if ($salescode || $spvName) {
                    $branchIds = DB::table('master_spvs')
                        ->where('is_active', true)
                        ->where(function ($q) use ($salescode, $spvName) {
                            if ($salescode) $q->where('salescode', 'ILIKE', $salescode);
                            if ($spvName) $q->orWhere('nama', 'ILIKE', $spvName);
                        })
                        ->pluck('branch_id')
                        ->filter()
                        ->unique()
                        ->toArray();
                }
                if (empty($branchIds) && $branchId) {
                    $branchIds = [$branchId];
                }

                $principalNames = [];
                if (!empty($branchIds)) {
                    $principalNames = DB::table('master_branches')
                        ->whereIn('branch_id', $branchIds)
                        ->whereNotNull('principal_name')
                        ->where('principal_name', '!=', '')
                        ->pluck('principal_name')
                        ->unique()
                        ->filter()
                        ->values()
                        ->toArray();
                }

                $principalTitle = !empty($principalNames) ? implode(' & ', $principalNames) : 'INAFOODS';

                $authUser = (object) array_merge((array) $spvUser, [
                    'principal_name' => $principalTitle,
                ]);
            }
        } elseif ($request->is('admin*') || $request->is('distributor*')) {
            $authUser = session('distributor_user') ?? $request->user();
        } else {
            $authUser = $request->user();
        }

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $authUser,
            ],
            'flash' => [
                'success' => session('success'),
                'error' => session('error') ?? (session('errors') ? session('errors')->first('error') : null),
                'info' => session('info'),
            ],
        ];
    }
}
