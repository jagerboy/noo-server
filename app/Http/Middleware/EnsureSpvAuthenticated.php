<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSpvAuthenticated
{
    /**
     * Handle an incoming request for SPV Area routes.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!session()->has('spv_user') && !$request->user()) {
            if ($request->expectsJson() || $request->isMethod('POST')) {
                return response()->json(['message' => 'Unauthenticated. Sesi SPV Area telah berakhir.'], 401);
            }
            return redirect()->route('spv_login.create')->withErrors(['username' => 'Silakan login sebagai SPV Area terlebih dahulu.']);
        }

        return $next($request);
    }
}
