<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class SanctumAbilitiesCheck
{
    /**
     * Handle an incoming request.
     *
     * @return mixed
     */
    public function handle(Request $request, Closure $next, ...$abilities)
    {
        foreach ($abilities as $ability) {
            if (! $request->user()->tokenCan($ability)) {
                abort(403, 'Forbidden');
            }
        }

        return $next($request);
    }
}
