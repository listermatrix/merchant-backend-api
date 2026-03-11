<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Exceptions\MasterTokenExpiredException;

class CheckIsMasterTokenMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        if ($request->user()) {

            $token = $request->user()->currentAccessToken();

            if ($token?->name !== 'master-token') {
             
                throw new MasterTokenExpiredException;
             }

             return $next($request);
        }

        throw new MasterTokenExpiredException;

    }
}
