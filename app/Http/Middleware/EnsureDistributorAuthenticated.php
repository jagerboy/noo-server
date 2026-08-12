<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureDistributorAuthenticated
{
    /**
     * Handle an incoming request for Admin Distributor routes.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!session()->has('distributor_user') && !$request->user()) {
            if ($request->expectsJson() || $request->isMethod('POST')) {
                return response()->json(['message' => 'Unauthenticated. Sesi Admin Distributor telah berakhir.'], 401);
            }
            return redirect()->route('distributor_login.create')->withErrors(['pin_branch' => 'Silakan login sebagai Admin Distributor terlebih dahulu.']);
        }

        return $next($request);
    }
}
