<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware untuk menginjeksi Security Headers ke setiap respon HTTP & API.
 * Memenuhi standar OWASP dan proteksi terhadap XSS, Clickjacking, MIME-sniffing, dan MITM.
 */
class SecurityHeadersMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        // 1. Mencegah MIME-Type Sniffing (Penting untuk keamanan file upload)
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        // 2. Mencegah Clickjacking (Frame Embedding Protection)
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');

        // 3. Filter XSS untuk browser legacy
        $response->headers->set('X-XSS-Protection', '1; mode=block');

        // 4. Referrer Policy aman saat navigasi antar domain
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        // 5. Pembatasan akses fitur browser (Kamera, Mikrofon, dll)
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(self)');

        // 6. Strict Transport Security (HSTS) - Enforce HTTPS untuk domain noo.coreappl.id
        if ($request->isSecure() || $request->header('X-Forwarded-Proto') === 'https' || app()->isProduction()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains; preload');
        }

        // 7. Content Security Policy (CSP) yang kompatibel dengan Inertia.js, Vue 3, Vite, dan Google Fonts
        $csp = [
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline' 'unsafe-eval'",
            "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com",
            "font-src 'self' https://fonts.gstatic.com data:",
            "img-src 'self' data: blob: https: https://noo.coreappl.id",
            "connect-src 'self' https: https://noo.coreappl.id wss:",
            "frame-ancestors 'self'",
            "upgrade-insecure-requests",
        ];
        $response->headers->set('Content-Security-Policy', implode('; ', $csp));

        return $response;
    }
}
