<?php

namespace App\Providers;

use App\Observers\AuditObserver;
use App\Services\AuditTrail;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(AuditTrail::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        foreach (array_keys(AuditObserver::MODELS) as $model) {
            $model::observe(AuditObserver::class);
        }
    }
}
