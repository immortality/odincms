<?php

namespace App\Providers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\ServiceProvider;

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
        // N+1 queries fail loudly outside production (tests and local development):
        // eager-load relations with with() / load() instead.
        Model::preventLazyLoading(! $this->app->isProduction());
    }
}
