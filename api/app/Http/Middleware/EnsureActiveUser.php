<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureActiveUser
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->user()?->isDisabled()) {
            abort(403, 'Account disabled');
        }

        return $next($request);
    }
}
