<?php

namespace App\Http\Middleware;

use App\Exceptions\CustomBadRequestException;
use Carbon\Carbon;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BlockOldAccounts
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        $user = Auth::user();

        $brijTesters = explode('|', config('brij.auth.brij_tester_client_ids'));
        $cutOffDate = Carbon::createFromDate(config('brij.auth.disable_users_before_date'));

        if ($user) {
            $isATester = in_array($user->client_id, $brijTesters);
            $accountIsOld = $user->created_at->lt($cutOffDate);

            throw_if(
                ! $isATester && $accountIsOld,
                new CustomBadRequestException('Sorry! Account under review. Please contact support.')
            );
        }

        return $next($request);
    }
}
