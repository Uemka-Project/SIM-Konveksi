<?php

namespace App\Providers;

use App\Models\User;
use App\Support\AccessMatrix;
use Illuminate\Support\Facades\Gate;
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
        // BOM is a pivot table, so it does not have a model policy.
        Gate::define('bom.view', fn (User $user): bool => AccessMatrix::can($user, 'bom', 'R'));
        Gate::define('bom.update', fn (User $user): bool => AccessMatrix::can($user, 'bom', 'U'));
    }
}
