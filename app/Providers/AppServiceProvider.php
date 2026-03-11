<?php

namespace App\Providers;

use App\Http\Services\Metrics\TracerService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register() : void
    {


    }

    /**
     * Bootstrap any application services.
     *
     * @param TracerService $tracerService
     * @return void
     */
    public function boot(TracerService $tracerService): void
    {

    }
}
