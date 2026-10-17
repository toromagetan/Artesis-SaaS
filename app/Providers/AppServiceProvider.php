<?php

namespace App\Providers;

use App\Modules\Identity\Models\User;
use App\Modules\Identity\Policies\UserPolicy;
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
     * Policy modul didaftarkan eksplisit di sini (namespace modul tidak
     * mengikuti konvensi penebakan Laravel) — lihat docs/POLA-POLICY.md.
     */
    public function boot(): void
    {
        Gate::policy(User::class, UserPolicy::class);
    }
}
