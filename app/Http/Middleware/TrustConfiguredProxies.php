<?php

namespace App\Http\Middleware;

use App\Support\TrustedProxyList;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TrustConfiguredProxies
{
    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next): Response
    {
        // Load configuration at request time, not during unbootstrapped CLI kernel
        // resolution. No hostname/platform-based automatic trust-all fallback.
        $request::setTrustedProxies(
            TrustedProxyList::parse((string) config('security.trusted_proxies', ''))['addresses'],
            Request::HEADER_X_FORWARDED_FOR | Request::HEADER_X_FORWARDED_PORT | Request::HEADER_X_FORWARDED_PROTO,
        );

        return $next($request);
    }
}
