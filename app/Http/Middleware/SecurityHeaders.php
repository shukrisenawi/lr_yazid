<?php

namespace App\Http\Middleware;

use App\Support\HttpSecurity;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next): Response
    {
        return HttpSecurity::apply($next($request), $request);
    }
}
