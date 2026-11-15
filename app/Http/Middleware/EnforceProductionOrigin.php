<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnforceProductionOrigin
{
    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next): Response
    {
        if (app()->isProduction()) {
            $url = (string) config('app.url');
            $host = strtolower(rtrim((string) parse_url($url, PHP_URL_HOST), '.'));
            $port = parse_url($url, PHP_URL_PORT) ?? 443;
            abort_unless(strtolower((string) parse_url($url, PHP_URL_SCHEME)) === 'https' && $host !== ''
                && $request->isSecure() && strtolower(rtrim($request->getHost(), '.')) === $host
                && $request->getPort() === $port, 400, 'Invalid application origin.');
        }

        return $next($request);
    }
}
