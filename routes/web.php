<?php

use App\Http\Controllers\LocaleController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SearchController;
use App\Modules\Accounting\Http\Controllers\AccountController;
use App\Modules\Accounting\Http\Controllers\FinancialReportController;
use App\Modules\Accounting\Http\Controllers\JournalController;
use App\Modules\Accounting\Http\Controllers\LedgerController;
use App\Modules\Catalog\Http\Controllers\ProductController;
use App\Modules\Clinical\Http\Controllers\PrescriptionController;
use App\Modules\CRM\Http\Controllers\CustomerController;
use App\Modules\Identity\Http\Controllers\RoleController;
use App\Modules\Identity\Http\Controllers\UserController;
use App\Modules\Inventory\Http\Controllers\InitialStockImportController;
use App\Modules\Inventory\Http\Controllers\StockController;
use App\Modules\Inventory\Http\Controllers\TransferController;
use App\Modules\Lab\Http\Controllers\LabController;
use App\Modules\Master\Http\Controllers\BranchController;
use App\Modules\Platform\Http\Controllers\DashboardController;
use App\Modules\Purchasing\Http\Controllers\CollectivePaymentController;
use App\Modules\Purchasing\Http\Controllers\PurchaseOrderController;
use App\Modules\Purchasing\Http\Controllers\PurchaseRequestController;
use App\Modules\Purchasing\Http\Controllers\ReceiptController;
use App\Modules\Reporting\Http\Controllers\ReportController;
use App\Modules\Sales\Http\Controllers\SaleController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::post('/bahasa/{locale}', [LocaleController::class, 'switch'])->name('locale.switch');

Route::get('/dashboard', DashboardController::class)
    ->middleware(['auth'])->name('dashboard');

Route::middleware('auth')->group(function () {
    // Pencarian global (Blueprint F.2 butir 9)
    Route::get('/cari', [SearchController::class, 'index'])->name('search');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // ---- Pengelolaan (Platform): pengguna, peran, cabang ----
    Route::middleware('permission:users.manage')->group(function () {
        Route::get('/pengguna', [UserController::class, 'index'])->name('users.index');
        Route::get('/pengguna/cetak', [UserController::class, 'printList'])->name('users.print');
        Route::get('/pengguna/buat', [UserController::class, 'create'])->name('users.create');
        Route::post('/pengguna', [UserController::class, 'store'])->name('users.store');
        Route::get('/pengguna/{user}', [UserController::class, 'show'])->name('users.show');
        Route::get('/pengguna/{user}/cetak', [UserController::class, 'printProfile'])->name('users.print-profile');
        Route::get('/pengguna/{user}/ubah', [UserController::class, 'edit'])->name('users.edit');
        Route::put('/pengguna/{user}', [UserController::class, 'update'])->name('users.update');
        Route::post('/pengguna/{user}/alih-aktif', [UserController::class, 'toggleActive'])->name('users.toggle-active');
    });

    Route::middleware('permission:roles.manage')->group(function () {
        Route::get('/peran', [RoleController::class, 'index'])->name('roles.index');
        Route::get('/peran/{role}/ubah', [RoleController::class, 'edit'])->name('roles.edit');
        Route::put('/peran/{role}', [RoleController::class, 'update'])->name('roles.update');
    });

    // ---- Pembelian (M.6) ----
    Route::get('/pembelian/pr', [PurchaseRequestController::class, 'index'])->middleware('permission:pr.view')->name('pr.index');
    Route::get('/pembelian/pr/baru', [PurchaseRequestController::class, 'create'])->middleware('permission:pr.create')->name('pr.create');
    Route::post('/pembelian/pr', [PurchaseRequestController::class, 'store'])->middleware('permission:pr.create')->name('pr.store');
    Route::post('/pembelian/pr/gabung', [PurchaseRequestController::class, 'convertMultiple'])->middleware('permission:pr.approve')->name('pr.convert-multiple');
    Route::get('/pembelian/pr/{pr}', [PurchaseRequestController::class, 'show'])->middleware('permission:pr.view')->name('pr.show');
    Route::post('/pembelian/pr/{pr}/submit', [PurchaseRequestController::class, 'submit'])->middleware('permission:pr.create')->name('pr.submit');
    Route::post('/pembelian/pr/{pr}/approve', [PurchaseRequestController::class, 'approve'])->middleware('permission:pr.approve')->name('pr.approve');
    Route::post('/pembelian/pr/{pr}/batal', [PurchaseRequestController::class, 'cancel'])->middleware('permission:pr.create')->name('pr.cancel');
    Route::post('/pembelian/pr/{pr}/konversi', [PurchaseRequestController::class, 'convert'])->middleware('permission:pr.approve')->name('pr.convert');

    Route::get('/pembelian/po', [PurchaseOrderController::class, 'index'])->middleware('permission:po.view')->name('po.index');
    Route::get('/pembelian/po/baru', [PurchaseOrderController::class, 'create'])->middleware('permission:po.create')->name('po.create');
    Route::post('/pembelian/po', [PurchaseOrderController::class, 'store'])->middleware('permission:po.create')->name('po.store');
    Route::get('/pembelian/po/{po}', [PurchaseOrderController::class, 'show'])->middleware('permission:po.view')->name('po.show');
    Route::post('/pembelian/po/{po}/kirim', [PurchaseOrderController::class, 'send'])->middleware('permission:po.update')->name('po.send');
    Route::post('/pembelian/po/{po}/void', [PurchaseOrderController::class, 'void'])->middleware('permission:po.void')->name('po.void');
    Route::get('/pembelian/po/{po}/terima', [PurchaseOrderController::class, 'receiveForm'])->middleware('permission:receipts.create')->name('po.receive');
    Route::post('/pembelian/po/{po}/terima', [PurchaseOrderController::class, 'storeReceipt'])->middleware('permission:receipts.create')->name('po.receive.store');
    Route::get('/pembelian/po/{po}/cetak', [PurchaseOrderController::class, 'print'])->middleware('permission:po.view')->name('po.print');

    Route::get('/pembelian/penerimaan/{receipt}', [ReceiptController::class, 'show'])->middleware('permission:po.view')->name('receipts.show');
    Route::get('/pembelian/penerimaan/{receipt}/bayar', [ReceiptController::class, 'payForm'])->middleware('permission:payments.create')->name('receipts.pay');
    Route::post('/pembelian/penerimaan/{receipt}/bayar', [ReceiptController::class, 'storePayment'])->middleware('permission:payments.create')->name('receipts.pay.store');
    Route::get('/pembelian/penerimaan/{receipt}/retur', [ReceiptController::class, 'returnForm'])->middleware('permission:purchases.return')->name('receipts.return');
    Route::post('/pembelian/penerimaan/{receipt}/retur', [ReceiptController::class, 'storeReturn'])->middleware('permission:purchases.return')->name('receipts.return.store');

    Route::get('/pembelian/bayar-kolektif', [CollectivePaymentController::class, 'form'])->middleware('permission:payments.collective')->name('payments.collective');
    Route::post('/pembelian/bayar-kolektif', [CollectivePaymentController::class, 'process'])->middleware('permission:payments.collective')->name('payments.collective.process');
    Route::get('/pembelian/bayar-kolektif/{payment}', [CollectivePaymentController::class, 'result'])->middleware('permission:payments.collective')->name('payments.collective.result');

    // ---- Lab (M.5) ----
    Route::get('/lab', [LabController::class, 'index'])->middleware('permission:lab.view')->name('lab.index');
    Route::get('/lab/{process}', [LabController::class, 'show'])->middleware('permission:lab.view')->name('lab.show');
    Route::post('/lab/{process}/tugaskan', [LabController::class, 'assign'])->middleware('permission:lab.assign')->name('lab.assign');
    Route::post('/lab/{process}/status', [LabController::class, 'transition'])->middleware('permission:lab.assign')->name('lab.transition');
    Route::post('/lab/{process}/selesai', [LabController::class, 'complete'])->middleware('permission:lab.complete')->name('lab.complete');

    // ---- Stok (M.4) & Transfer (M.7) ----
    Route::get('/stok', [StockController::class, 'index'])->middleware('permission:stock.view')->name('stock.index');
    Route::get('/stok/pergerakan', [StockController::class, 'movements'])->middleware('permission:stock.view')->name('stock.movements');
    Route::get('/transfer', [TransferController::class, 'index'])->middleware('permission:stock.view')->name('transfers.index');
    Route::get('/transfer/baru', [TransferController::class, 'create'])->middleware('permission:stock.transfer')->name('transfers.create');
    Route::post('/transfer', [TransferController::class, 'store'])->middleware('permission:stock.transfer')->name('transfers.store');
    Route::get('/transfer/{transfer}', [TransferController::class, 'show'])->middleware('permission:stock.view')->name('transfers.show');
    Route::post('/transfer/{transfer}/terima', [TransferController::class, 'receive'])->middleware('permission:stock.receive_transfer')->name('transfers.receive');
    Route::post('/transfer/{transfer}/batal', [TransferController::class, 'cancel'])->middleware('permission:stock.transfer')->name('transfers.cancel');
    Route::get('/transfer/{transfer}/cetak', [TransferController::class, 'print'])->middleware('permission:stock.view')->name('transfers.print');
    Route::post('/pembelian/po/{po}/transfer', [TransferController::class, 'storeFromPo'])->middleware('permission:stock.transfer')->name('po.transfer.store');
    Route::get('/stok/impor', [InitialStockImportController::class, 'form'])->name('stock.import.form');
    Route::get('/stok/impor/template', [InitialStockImportController::class, 'template'])->name('stock.import.template');
    Route::post('/stok/impor/pratinjau', [InitialStockImportController::class, 'preview'])->name('stock.import.preview');
    Route::post('/stok/impor/komitmen', [InitialStockImportController::class, 'commit'])->name('stock.import.commit');

    // ---- Akuntansi (M.9) ----
    Route::get('/jurnal', [JournalController::class, 'index'])->middleware('permission:journals.view')->name('journals.index');
    Route::get('/jurnal/buat', [JournalController::class, 'create'])->middleware('permission:journals.manual')->name('journals.create');
    Route::post('/jurnal', [JournalController::class, 'store'])->middleware('permission:journals.manual')->name('journals.store');
    Route::get('/buku-besar', [LedgerController::class, 'index'])->middleware('permission:journals.view')->name('ledger.index');
    Route::get('/jurnal/{journal}', [JournalController::class, 'show'])->middleware('permission:journals.view')->name('journals.show');
    Route::post('/jurnal/{journal}/balik', [JournalController::class, 'reverse'])->middleware('permission:journals.reverse')->name('journals.reverse');
    Route::get('/akun', [AccountController::class, 'index'])->middleware('permission:accounts.view')->name('accounts.index');
    Route::get('/akun/buat', [AccountController::class, 'create'])->middleware('permission:accounts.manage')->name('accounts.create');
    Route::post('/akun', [AccountController::class, 'store'])->middleware('permission:accounts.manage')->name('accounts.store');
    Route::get('/akun/{account}', [AccountController::class, 'show'])->middleware('permission:accounts.view')->name('accounts.show');
    Route::get('/akun/{account}/ubah', [AccountController::class, 'edit'])->middleware('permission:accounts.manage')->name('accounts.edit');
    Route::put('/akun/{account}', [AccountController::class, 'update'])->middleware('permission:accounts.manage')->name('accounts.update');
    Route::post('/akun/{account}/aktif', [AccountController::class, 'toggleActive'])->middleware('permission:accounts.manage')->name('accounts.toggle-active');

    // ---- Laporan inti (M.10) ----
    Route::get('/laporan/penjualan', [ReportController::class, 'sales'])->middleware('permission:reports.sales')->name('reports.sales');
    Route::get('/laporan/pembelian', [ReportController::class, 'purchases'])->middleware('permission:reports.purchases')->name('reports.purchases');
    Route::get('/laporan/stok', [ReportController::class, 'stock'])->middleware('permission:reports.stock')->name('reports.stock');
    Route::get('/laporan/utang', [ReportController::class, 'payables'])->middleware('permission:reports.purchases')->name('reports.payables');
    Route::get('/laporan/neraca', [FinancialReportController::class, 'balanceSheet'])->middleware('permission:reports.financial')->name('financial.balance-sheet');
    Route::get('/laporan/laba-rugi', [FinancialReportController::class, 'incomeStatement'])->middleware('permission:reports.financial')->name('financial.income');
    Route::get('/laporan/arus-kas', [FinancialReportController::class, 'cashFlow'])->middleware('permission:reports.financial')->name('financial.cash-flow');

    // ---- Penjualan (M.3) ----
    Route::get('/penjualan', [SaleController::class, 'index'])->middleware('permission:sales.view')->name('sales.index');
    Route::get('/penjualan/baru', [SaleController::class, 'create'])->middleware('permission:sales.create')->name('sales.create');
    Route::post('/penjualan', [SaleController::class, 'store'])->middleware('permission:sales.create')->name('sales.store');
    Route::get('/penjualan/{sale}', [SaleController::class, 'show'])->middleware('permission:sales.view')->name('sales.show');
    Route::get('/penjualan/{sale}/bayar', [SaleController::class, 'paymentForm'])->middleware('permission:sales.pay')->name('sales.payment');
    Route::post('/penjualan/{sale}/bayar', [SaleController::class, 'storePayment'])->middleware('permission:sales.pay')->name('sales.payment.store');
    Route::get('/penjualan/{sale}/void', [SaleController::class, 'voidForm'])->middleware('permission:sales.void')->name('sales.void');
    Route::post('/penjualan/{sale}/void', [SaleController::class, 'storeVoid'])->middleware('permission:sales.void')->name('sales.void.store');
    Route::get('/penjualan/{sale}/retur', [SaleController::class, 'returnForm'])->middleware('permission:sales.return')->name('sales.return');
    Route::post('/penjualan/{sale}/retur', [SaleController::class, 'storeReturn'])->middleware('permission:sales.return')->name('sales.return.store');
    Route::get('/penjualan/{sale}/cetak/orderan', [SaleController::class, 'printOrder'])->middleware('permission:sales.view')->name('sales.print-order');
    Route::get('/penjualan/{sale}/cetak/invoice', [SaleController::class, 'printInvoice'])->middleware('permission:sales.view')->name('sales.print-invoice');
    Route::get('/penjualan/{sale}/cetak/resep', [SaleController::class, 'printPrescription'])->middleware('permission:sales.view')->name('sales.print-prescription');

    // ---- Pelanggan & Resep (M.2) ----
    Route::get('/pelanggan', [CustomerController::class, 'index'])->middleware('permission:customers.view')->name('customers.index');
    Route::get('/pelanggan/buat', [CustomerController::class, 'create'])->middleware('permission:customers.create')->name('customers.create');
    Route::post('/pelanggan', [CustomerController::class, 'store'])->middleware('permission:customers.create')->name('customers.store');
    Route::get('/pelanggan/{customer}', [CustomerController::class, 'show'])->middleware('permission:customers.view')->name('customers.show');
    Route::get('/pelanggan/{customer}/ubah', [CustomerController::class, 'edit'])->middleware('permission:customers.update')->name('customers.edit');
    Route::put('/pelanggan/{customer}', [CustomerController::class, 'update'])->middleware('permission:customers.update')->name('customers.update');
    Route::post('/pelanggan/{customer}/alih-aktif', [CustomerController::class, 'toggleActive'])->middleware('permission:customers.deactivate')->name('customers.toggle-active');

    Route::get('/pelanggan/{customer}/resep/baru', [PrescriptionController::class, 'create'])->middleware('permission:prescriptions.create')->name('prescriptions.create');
    Route::post('/pelanggan/{customer}/resep', [PrescriptionController::class, 'store'])->middleware('permission:prescriptions.create')->name('prescriptions.store');
    Route::get('/resep/{prescription}', [PrescriptionController::class, 'show'])->middleware('permission:prescriptions.view')->name('prescriptions.show');
    Route::get('/resep/{prescription}/ubah', [PrescriptionController::class, 'edit'])->middleware('permission:prescriptions.create')->name('prescriptions.edit');
    Route::put('/resep/{prescription}', [PrescriptionController::class, 'update'])->middleware('permission:prescriptions.create')->name('prescriptions.update');
    Route::post('/resep/{prescription}/duplikat', [PrescriptionController::class, 'duplicate'])->middleware('permission:prescriptions.create')->name('prescriptions.duplicate');
    Route::get('/resep/{prescription}/cetak', [PrescriptionController::class, 'print'])->middleware('permission:prescriptions.print')->name('prescriptions.print');
    Route::get('/resep/{prescription}/precal', [PrescriptionController::class, 'precal'])->middleware('permission:prescriptions.view')->name('prescriptions.precal');
    Route::get('/resep/{prescription}/precal/cetak', [PrescriptionController::class, 'precalPrint'])->middleware('permission:prescriptions.view')->name('prescriptions.precal-print');

    // ---- Katalog (M.1) ----
    Route::get('/katalog/{type}', [ProductController::class, 'index'])->middleware('permission:products.view')->name('products.index');
    Route::get('/katalog/{type}/buat', [ProductController::class, 'create'])->middleware('permission:products.create')->name('products.create');
    Route::post('/katalog/{type}', [ProductController::class, 'store'])->middleware('permission:products.create')->name('products.store');
    Route::get('/produk/{product}', [ProductController::class, 'show'])->middleware('permission:products.view')->name('products.show');
    Route::get('/produk/{product}/ubah', [ProductController::class, 'edit'])->middleware('permission:products.update')->name('products.edit');
    Route::put('/produk/{product}', [ProductController::class, 'update'])->middleware('permission:products.update')->name('products.update');
    Route::post('/produk/{product}/alih-aktif', [ProductController::class, 'toggleActive'])->middleware('permission:products.deactivate')->name('products.toggle-active');

    Route::middleware('permission:branches.manage')->group(function () {
        Route::get('/cabang', [BranchController::class, 'index'])->name('branches.index');
        Route::get('/cabang/buat', [BranchController::class, 'create'])->name('branches.create');
        Route::post('/cabang', [BranchController::class, 'store'])->name('branches.store');
        Route::get('/cabang/{branch}', [BranchController::class, 'show'])->name('branches.show');
        Route::get('/cabang/{branch}/ubah', [BranchController::class, 'edit'])->name('branches.edit');
        Route::put('/cabang/{branch}', [BranchController::class, 'update'])->name('branches.update');
        Route::post('/cabang/{branch}/alih-aktif', [BranchController::class, 'toggleActive'])->name('branches.toggle-active');
    });
});

require __DIR__.'/auth.php';
