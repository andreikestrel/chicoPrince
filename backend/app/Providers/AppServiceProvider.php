<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Sanctum;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // A SPA usa apenas sessão; não emitimos nem consultamos tokens pessoais.
        Sanctum::getAccessTokenFromRequestUsing(static fn () => null);
    }
}
