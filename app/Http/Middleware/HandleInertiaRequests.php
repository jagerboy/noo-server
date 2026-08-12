<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
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
            $authUser = session('spv_user') ?? $request->user();
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
