<?php

namespace App\Providers;

use App\Modules\Identity\Models\User;
use App\Modules\Identity\Policies\UserPolicy;
use App\Modules\Inventory\Listeners\SyncStockWithSales;
use App\Modules\Lab\Listeners\SyncLensProcessWithSales;
use App\Modules\Sales\Events\SaleCompleted;
use App\Modules\Sales\Events\SaleReturned;
use App\Modules\Sales\Events\SaleVoided;
use App\Support\PermissionRegistry;
use Illuminate\Support\Facades\Event;
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

        // Jembatan @can()/Gate ke registry izin: ability yang merupakan
        // string izin registry dijawab oleh User::hasPermission();
        // ability lain (policy: view/update/…) diteruskan ke policy.
        Gate::before(function (User $user, string $ability) {
            if (PermissionRegistry::exists($ability)) {
                return $user->hasPermission($ability);
            }

            return null;
        });

        // Integrasi antar-modul lewat peristiwa domain (A.2):
        // Inventory menyinkronkan stok dari peristiwa Sales.
        Event::listen(SaleCompleted::class, [SyncStockWithSales::class, 'onSaleCompleted']);
        Event::listen(SaleVoided::class, [SyncStockWithSales::class, 'onSaleVoided']);
        Event::listen(SaleReturned::class, [SyncStockWithSales::class, 'onSaleReturned']);
        Event::listen(SaleCompleted::class, [SyncLensProcessWithSales::class, 'onSaleCompleted']);
        Event::listen(SaleVoided::class, [SyncLensProcessWithSales::class, 'onSaleVoided']);
    }
}
