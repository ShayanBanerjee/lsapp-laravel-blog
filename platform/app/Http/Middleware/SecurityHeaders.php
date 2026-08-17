<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Baseline security headers.
 *
 * The CSP is the load-bearing one here: post bodies are author-supplied HTML
 * rendered with dangerouslySetInnerHTML, so it is the second line of defence
 * behind HtmlSanitizer. If the sanitizer is ever bypassed, the CSP is what
 * stops an injected <script> from executing.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('X-Permitted-Cross-Domain-Policies', 'none');
        $response->headers->set(
            'Permissions-Policy',
            'camera=(), microphone=(), geolocation=(), payment=(), usb=(), interest-cohort=()'
        );

        // HSTS only over TLS — sending it on plaintext dev traffic is noise, and
        // sending it from a non-HTTPS origin is ignored anyway.
        if ($request->secure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        $response->headers->set('Content-Security-Policy', $this->policy());

        return $response;
    }

    private function policy(): string
    {
        $isLocal = app()->environment('local');

        // In development the assets are served by Vite on a *different origin*
        // (a separate port), which 'self' does not cover. Without this the HMR
        // client is blocked and the page renders blank.
        $dev = $isLocal ? $this->viteDevOrigin() : null;
        $devHttp = $dev ? ' '.$dev : '';
        $devWs = $dev ? ' '.preg_replace('#^http#', 'ws', $dev) : '';

        // Inertia ships page props in a data attribute rather than an inline
        // script, so 'unsafe-inline'/'unsafe-eval' are only needed for Vite's
        // dev client — production keeps a strict script-src.
        $script = $isLocal
            ? "script-src 'self' 'unsafe-inline' 'unsafe-eval'".$devHttp
            : "script-src 'self'";

        return implode('; ', [
            "default-src 'self'",
            $script,
            "style-src 'self' 'unsafe-inline' https://fonts.bunny.net".$devHttp,
            'font-src \'self\' https://fonts.bunny.net data:'.$devHttp,
            "img-src 'self' data: blob:".$devHttp,
            "connect-src 'self'".$devHttp.$devWs,
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'none'",
        ]);
    }

    /**
     * The origin Vite is serving dev assets from, or null when running against
     * built assets.
     *
     * Read from Vite's hot file rather than hardcoding :5173 — Vite picks the
     * next free port when that one is taken, and a hardcoded port would break
     * exactly when two projects are open at once.
     */
    private function viteDevOrigin(): ?string
    {
        $hotFile = public_path('hot');

        if (! is_file($hotFile)) {
            return null;
        }

        $url = trim((string) file_get_contents($hotFile));

        return preg_match('#^https?://[^/\s]+#i', $url, $matches) ? $matches[0] : null;
    }
}
