<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class SetSentryContext
{
    /**
     * Handle an incoming request.
     *
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        if (auth()->check()) {
            \Sentry\configureScope(function (\Sentry\State\Scope $scope): void {
                $user = auth()->user();
                $scope->setUser(['email' => $user->email, 'id' => $user->id, 'username' => $user->firstname]);
            });
        }

        return $next($request);
    }
}
