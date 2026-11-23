<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CustomerOnly
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->role === UserRole::Admin) {
            if ($request->isMethod('GET') && ! $request->routeIs('vip.*', 'appointments.book')) {
                return redirect()->route('admin.dashboard');
            }

            abort(403, 'Personal shopping and membership features are for customers only.');
        }

        return $next($request);
    }
}
