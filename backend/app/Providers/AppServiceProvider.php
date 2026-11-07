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

        // The PRD limits each report to the roles that work with its data.
        Gate::define(
            'reports.sales',
            fn (User $user): bool => $user->isOwner() || $user->isAdmin(),
        );
        Gate::define(
            'reports.purchases',
            fn (User $user): bool => $user->isOwner() || $user->isAdmin(),
        );
        Gate::define(
            'reports.stock_card',
            fn (User $user): bool => $user->isOwner()
                || $user->isAdmin()
                || $user->hasRole('gudang'),
        );
        Gate::define(
            'reports.productivity',
            fn (User $user): bool => $user->isOwner()
                || $user->isAdmin()
                || $user->hasRole('produksi'),
        );
    }
}
