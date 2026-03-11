<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class SecurityHeaderMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param Request $request
     * @param  \Closure(Request): (Response|RedirectResponse)  $next
     * @return Response|RedirectResponse
     */
    public function handle(Request $request, Closure $next): Response|RedirectResponse
    {
        $response = $next($request);

       
        $response->headers->set('X-Frame-Options', 'DENY');  // Clickjacking protection

        header_remove('X-Powered-By');
        $response->headers->set('Server', 'secure');

        return $response;
    }
}
