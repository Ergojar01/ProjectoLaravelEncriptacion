<?php

namespace App\Providers;

use App\Models\User;
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
        Gate::define('view-admin-records', fn (User $user): bool => in_array($user->role_level, [499, 500], true));
        Gate::define('manage-users', fn (User $user): bool => $user->role_level === 500);
        Gate::define('view-password-hashes', fn (User $user): bool => $user->role_level === 500);
    }
}
