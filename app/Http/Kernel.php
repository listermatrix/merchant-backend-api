<?php

namespace App\Http;

use Fruitcake\Cors\HandleCors;
use App\Http\Middleware\TrimStrings;
use App\Http\Middleware\Authenticate;
use App\Http\Middleware\TrustProxies;
use App\Http\Middleware\EncryptCookies;
use App\Http\Middleware\VerifyCsrfToken;
use App\Http\Middleware\BlockOldAccounts;
use App\Http\Middleware\SetSentryContext;
use App\Http\Middleware\TracerMiddleware;
use Illuminate\Auth\Middleware\Authorize;
use App\Http\Middleware\EnsureUserIsAdmin;
use App\Http\Middleware\FirewallMiddleware;
use App\Http\Middleware\IoLoggerMiddleware;
use App\Http\Middleware\BlockUserMiddleware;
use App\Http\Middleware\EnsureUserIsMerchant;
use App\Http\Middleware\ForceHttpsMiddleware;
use App\Http\Middleware\SanctumAbilitiesCheck;
use App\Http\Middleware\UserActivityMiddleware;
use Illuminate\Auth\Middleware\RequirePassword;
use Illuminate\Http\Middleware\SetCacheHeaders;
use Illuminate\Session\Middleware\StartSession;
use App\Http\Middleware\RedirectIfAuthenticated;
use App\Http\Middleware\EnsureUserIsAccountOwner;
use App\Http\Middleware\SecurityHeaderMiddleware;
use Spatie\Permission\Middlewares\RoleMiddleware;
use App\Http\Middleware\PinAuthorizationMiddleware;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Foundation\Http\Kernel as HttpKernel;
use Illuminate\Routing\Middleware\ValidateSignature;
use App\Http\Middleware\BlockNegativeInputMiddleware;
use App\Http\Middleware\CheckIsMasterTokenMiddleware;
use Illuminate\Auth\Middleware\EnsureEmailIsVerified;
use Illuminate\Routing\Middleware\SubstituteBindings;
use App\Http\Middleware\GhanaCediKillSwitchMiddleware;
use App\Http\Middleware\WhitelistedClientIPMiddleware;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Spatie\Permission\Middlewares\PermissionMiddleware;
use App\Http\Middleware\CheckIsDeveloperTokenMiddleware;
use App\Http\Middleware\PreventRequestsDuringMaintenance;
use App\Http\Middleware\ThrottlePwbStatusCheckMiddleware;
use Illuminate\Auth\Middleware\AuthenticateWithBasicAuth;
use App\Http\Middleware\BrijxMerchantPermissionMiddleware;
use Illuminate\Foundation\Http\Middleware\ValidatePostSize;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use App\Http\Middleware\UserManagementAuthorizationMiddleware;
use Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;

class Kernel extends HttpKernel
{
    /**
     * The application's global HTTP middleware stack.
     *
     * These middleware are run during every request to your application.
     *
     * @var array
     */
    protected $middleware = [
        // L
        TrustProxies::class,
        HandleCors::class,
        ValidatePostSize::class,
        TrimStrings::class,
        ConvertEmptyStringsToNull::class,
        SetSentryContext::class,
        SecurityHeaderMiddleware::class,
    ];

    /**
     * The application's route middleware groups.
     *
     * @var array
     */
    protected $middlewareGroups = [
        'web' => [
            EncryptCookies::class,
            AddQueuedCookiesToResponse::class,
            StartSession::class,
            // \Illuminate\Session\Middleware\AuthenticateSession::class,
            ShareErrorsFromSession::class,
            VerifyCsrfToken::class,
            SubstituteBindings::class,
        ],

        'api' => [
            EnsureFrontendRequestsAreStateful::class,
            'throttle:1000,1',
            SubstituteBindings::class,
        ],
    ];

    /**
     * The application's route middleware.
     *
     * These middleware may be assigned to groups or used individually.
     *
     * @var array
     */
    protected $routeMiddleware = [
        'auth' => Authenticate::class,
        'auth.basic' => AuthenticateWithBasicAuth::class,
        'cache.headers' => SetCacheHeaders::class,
        'can' => Authorize::class,
        'guest' => RedirectIfAuthenticated::class,
        'password.confirm' => RequirePassword::class,
        'signed' => ValidateSignature::class,
        'throttle' => ThrottleRequests::class,
        'user.block-old-accounts' => BlockOldAccounts::class,
        'sanctum.abilities' => SanctumAbilitiesCheck::class,
        'token.master' => CheckIsMasterTokenMiddleware::class,

    ];
}
