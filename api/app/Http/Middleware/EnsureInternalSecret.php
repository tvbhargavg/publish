<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/** Guards /internal/* (tunnel-server to API). Fails closed if no secret is configured. */
class EnsureInternalSecret
{
    public function handle(Request $request, Closure $next)
    {
        $secret = (string) config('tunnel.internal_secret');
        if (strlen($secret) < 16) {
            abort(503, 'internal API not configured');
        }
        if (! hash_equals($secret, (string) $request->header('X-Internal-Secret'))) {
            abort(401);
        }

        return $next($request);
    }
}
