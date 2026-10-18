<?php

namespace App\Providers;

use App\Modules\Accounting\Listeners\AccrueCommissions;
use App\Modules\Accounting\Listeners\PostJournalForDomainEvents;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Policies\UserPolicy;
use App\Modules\Inventory\Events\InitialStockImported;
use App\Modules\Inventory\Listeners\SyncStockWithSales;
use App\Modules\Inventory\Policies\InitialStockImportPolicy;
use App\Modules\Lab\Events\LabOrderReceived;
use App\Modules\Lab\Events\LensProcessCompleted;
use App\Modules\Lab\Listeners\SyncLensProcessWithSales;
use App\Modules\Purchasing\Events\PurchasePaymentPosted;
use App\Modules\Purchasing\Events\PurchaseReturned;
use App\Modules\Purchasing\Events\ReceiptStored;
use App\Modules\Sales\Events\SaleCompleted;
use App\Modules\Sales\Events\SalePaymentRecorded;
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

        // Impor stok awal dipagari POLICY eksplisit (D.8/PRD 7.5),
        // bukan string izin: hanya Owner non-platform.
        Gate::define('stock.import-initial', fn (User $user) => app(InitialStockImportPolicy::class)->import($user));

        // Integrasi antar-modul lewat peristiwa domain (A.2):
        // Inventory menyinkronkan stok dari peristiwa Sales.
        Event::listen(SaleCompleted::class, [SyncStockWithSales::class, 'onSaleCompleted']);
        Event::listen(SaleVoided::class, [SyncStockWithSales::class, 'onSaleVoided']);
        Event::listen(SaleReturned::class, [SyncStockWithSales::class, 'onSaleReturned']);
        // Akuntansi (M.9/D.9): jurnal otomatis dari peristiwa domain.
        Event::listen(SaleCompleted::class, [PostJournalForDomainEvents::class, 'onSaleCompleted']);
        Event::listen(SalePaymentRecorded::class, [PostJournalForDomainEvents::class, 'onSalePaymentRecorded']);
        Event::listen(SaleVoided::class, [PostJournalForDomainEvents::class, 'onSaleVoided']);
        Event::listen(SaleReturned::class, [PostJournalForDomainEvents::class, 'onSaleReturned']);
        Event::listen(ReceiptStored::class, [PostJournalForDomainEvents::class, 'onReceiptStored']);
        Event::listen(PurchaseReturned::class, [PostJournalForDomainEvents::class, 'onPurchaseReturned']);
        Event::listen(PurchasePaymentPosted::class, [PostJournalForDomainEvents::class, 'onPurchasePaymentPosted']);
        Event::listen(InitialStockImported::class, [PostJournalForDomainEvents::class, 'onInitialStockImported']);

        Event::listen(SaleCompleted::class, [SyncLensProcessWithSales::class, 'onSaleCompleted']);
        Event::listen(SaleVoided::class, [SyncLensProcessWithSales::class, 'onSaleVoided']);
        // Komisi (F1.7): akrual dokter/teknisi dari data final.
        Event::listen(SaleCompleted::class, [AccrueCommissions::class, 'onSaleCompleted']);
        Event::listen(SaleVoided::class, [AccrueCommissions::class, 'onSaleVoided']);
        Event::listen(LensProcessCompleted::class, [AccrueCommissions::class, 'onLensProcessCompleted']);
        Event::listen(LabOrderReceived::class, [PostJournalForDomainEvents::class, 'onLabOrderReceived']);
    }
}
