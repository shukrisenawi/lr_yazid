<?php

namespace App\Support;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class HttpSecurity
{
    public static function apply(Response $response, Request $request): Response
    {
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        // Inertia validation redirects need the current same-origin form URL.
        // Do not disclose it when navigating to external sites.
        $response->headers->set('Referrer-Policy', 'same-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
        // Baseline only: not a script-src policy or a substitute for XSS sanitization.
        if (! $response->headers->has('Content-Security-Policy')) {
            $response->headers->set('Content-Security-Policy', "base-uri 'self'; object-src 'none'; frame-ancestors 'none'");
        }
        // Even public Inertia HTML shares session-specific auth/cart props.
        // Static assets are served separately by the web server and may be cached.
        $response->headers->set('Cache-Control', 'private, no-store');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', '0');
        if (app()->isProduction() && $request->isSecure()) {
            // No includeSubDomains/preload: ownership of other hosts is not assumed.
            $response->headers->set('Strict-Transport-Security', 'max-age=15552000');
        }

        return $response;
    }
}
