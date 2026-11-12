<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Security headers on every response of the app (student pages, admin panel,
 * JSON endpoints):
 *
 * - No other site may show our pages in a frame (clickjacking): X-Frame-Options
 *   and CSP frame-ancestors.
 * - Browsers must not guess file types (nosniff), and links to other sites
 *   carry only our domain, never the page address (Referrer-Policy).
 * - No plugins (object-src) and no <base> tag pointing elsewhere (base-uri).
 * - Camera, microphone, location and other device features are off.
 * - Over HTTPS: the browser keeps using HTTPS for a year (HSTS).
 *
 * The CSP is kept to these directives on purpose: limiting scripts and
 * styles would break Vite, Filament, Livewire and YouTube embeds.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $headers = $response->headers;

        $headers->set('X-Frame-Options', 'SAMEORIGIN');
        $headers->set('X-Content-Type-Options', 'nosniff');
        $headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), usb=(), payment=(), interest-cohort=()');
        $headers->set('Cross-Origin-Opener-Policy', 'same-origin-allow-popups');
        // A page that already sets its own policy keeps it.
        if (! $headers->has('Content-Security-Policy')) {
            $headers->set('Content-Security-Policy', "frame-ancestors 'self'; object-src 'none'; base-uri 'self'");
        }
        if ($request->isSecure()) {
            $headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }
}
