<?php

use App\Http\Controllers\LocaleController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SearchController;
use App\Modules\Accounting\Http\Controllers\AccountController;
use App\Modules\Accounting\Http\Controllers\CommissionController;
use App\Modules\Accounting\Http\Controllers\FinancialReportController;
use App\Modules\Accounting\Http\Controllers\FixedAssetController;
use App\Modules\Accounting\Http\Controllers\IncentiveController;
use App\Modules\Accounting\Http\Controllers\JournalController;
use App\Modules\Accounting\Http\Controllers\LedgerController;
use App\Modules\Accounting\Http\Controllers\PayrollController;
use App\Modules\Accounting\Http\Controllers\PeriodController;
use App\Modules\Catalog\Http\Controllers\ProductController;
use App\Modules\Catalog\Http\Controllers\ProductParentController;
use App\Modules\Clinical\Http\Controllers\FittingController;
use App\Modules\Clinical\Http\Controllers\PrescriptionController;
use App\Modules\Comms\Http\Controllers\WaCampaignController;
use App\Modules\Comms\Http\Controllers\WaGatewayController;
use App\Modules\Comms\Http\Controllers\WaInboxController;
use App\Modules\Comms\Http\Controllers\WaLogController;
use App\Modules\Comms\Http\Controllers\WaTemplateController;
use App\Modules\Comms\Http\Controllers\WaWebhookController;
use App\Modules\CRM\Http\Controllers\CustomerController;
use App\Modules\Identity\Http\Controllers\RoleController;
use App\Modules\Identity\Http\Controllers\UserController;
use App\Modules\Inventory\Http\Controllers\InitialStockImportController;
use App\Modules\Inventory\Http\Controllers\LabelController;
use App\Modules\Inventory\Http\Controllers\OpnameController;
use App\Modules\Inventory\Http\Controllers\StockController;
use App\Modules\Inventory\Http\Controllers\TransferController;
use App\Modules\Lab\Http\Controllers\LabController;
use App\Modules\Lab\Http\Controllers\QcTemplateController;
use App\Modules\Master\Http\Controllers\BranchController;
use App\Modules\Master\Http\Controllers\DoctorController;
use App\Modules\Master\Http\Controllers\InsuranceController;
use App\Modules\Master\Http\Controllers\SupplierController;
use App\Modules\Membership\Http\Controllers\MemberController;
use App\Modules\Membership\Http\Controllers\MemberPortalController;
use App\Modules\Membership\Http\Controllers\MembershipLevelController;
use App\Modules\Platform\Http\Controllers\AiSettingController;
use App\Modules\Platform\Http\Controllers\ApiTokenController;
use App\Modules\Platform\Http\Controllers\AssistantController;
use App\Modules\Platform\Http\Controllers\AuditLogController;
use App\Modules\Platform\Http\Controllers\BackupController;
use App\Modules\Platform\Http\Controllers\DashboardController;
use App\Modules\Platform\Http\Controllers\GeneralSettingsController;
use App\Modules\Purchasing\Http\Controllers\CollectivePaymentController;
use App\Modules\Purchasing\Http\Controllers\PurchaseOrderController;
use App\Modules\Purchasing\Http\Controllers\PurchaseRequestController;
use App\Modules\Purchasing\Http\Controllers\ReceiptController;
use App\Modules\Reporting\Http\Controllers\ReportController;
use App\Modules\Sales\Http\Controllers\CustomerCreditController;
use App\Modules\Sales\Http\Controllers\OfflineCashierController;
use App\Modules\Sales\Http\Controllers\OfflineExceptionController;
use App\Modules\Sales\Http\Controllers\ReceivableController;
use App\Modules\Sales\Http\Controllers\SaleController;
use App\Modules\Sales\Http\Controllers\Settings\OfflineDeviceController;
use App\Modules\Sales\Http\Controllers\WarrantyController;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    // Halaman muka bermerek Lentera (audit 23 Okt 2026 D1 — halaman
    // sambutan bawaan Laravel dihapus); pengguna login langsung ke
    // dashboard.
    return auth()->check() ? redirect()->route('dashboard') : view('landing');
});

Route::post('/bahasa/{locale}', [LocaleController::class, 'switch'])->name('locale.switch');

Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth'])->name('dashboard');
Route::get('/dashboard/data', [DashboardController::class, 'data'])
    ->middleware(['auth'])->name('dashboard.data');

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
    Route::post('/pembelian/pr/{pr}/batal', [PurchaseRequestController::class, 'cancel'])->middleware('permission:pr.cancel')->name('pr.cancel');
    Route::post('/pembelian/pr/{pr}/konversi', [PurchaseRequestController::class, 'convert'])->middleware('permission:pr.approve')->name('pr.convert');

    Route::get('/pembelian/po', [PurchaseOrderController::class, 'index'])->middleware('permission:po.view')->name('po.index');
    Route::get('/pembelian/po/baru', [PurchaseOrderController::class, 'create'])->middleware('permission:po.create')->name('po.create');
    Route::get('/pembelian/po/matriks.xlsx', [PurchaseOrderController::class, 'matrixExport'])->middleware('permission:po.create')->name('po.matrix-export');
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
    // Template QC (F2.1) — SEBELUM /lab/{process} agar tidak tertangkap wildcard.
    Route::get('/lab/template-qc', [QcTemplateController::class, 'index'])->middleware('permission:lab.view')->name('qc-templates.index');
    Route::get('/lab/template-qc/buat', [QcTemplateController::class, 'create'])->middleware('permission:lab.templates.manage')->name('qc-templates.create');
    Route::post('/lab/template-qc', [QcTemplateController::class, 'store'])->middleware('permission:lab.templates.manage')->name('qc-templates.store');
    Route::get('/lab/template-qc/{template}/ubah', [QcTemplateController::class, 'edit'])->middleware('permission:lab.templates.manage')->name('qc-templates.edit');
    Route::put('/lab/template-qc/{template}', [QcTemplateController::class, 'update'])->middleware('permission:lab.templates.manage')->name('qc-templates.update');
    Route::post('/lab/template-qc/{template}/aktif', [QcTemplateController::class, 'toggleActive'])->middleware('permission:lab.templates.manage')->name('qc-templates.toggle');

    // Order lab (F2.3) — juga sebelum /lab/{process}.

    Route::get('/lab', [LabController::class, 'index'])->middleware('permission:lab.view')->name('lab.index');
    Route::get('/lab/{process}', [LabController::class, 'show'])->middleware('permission:lab.view')->name('lab.show');
    Route::post('/lab/{process}/tugaskan', [LabController::class, 'assign'])->middleware('permission:lab.assign')->name('lab.assign');
    Route::post('/lab/{process}/status', [LabController::class, 'transition'])->middleware('permission:lab.assign')->name('lab.transition');
    Route::post('/lab/{process}/selesai', [LabController::class, 'complete'])->middleware('permission:lab.complete')->name('lab.complete');
    Route::post('/lab/{process}/qc', [LabController::class, 'recordQc'])->middleware('permission:lab.qc')->name('lab.qc.record');

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
    Route::get('/stok/opname', [OpnameController::class, 'index'])->middleware('permission:stock.opname')->name('opnames.index');
    Route::get('/stok/opname/baru', [OpnameController::class, 'create'])->middleware('permission:stock.opname')->name('opnames.create');
    Route::post('/stok/opname', [OpnameController::class, 'store'])->middleware('permission:stock.opname')->name('opnames.store');
    Route::get('/stok/opname/{opname}', [OpnameController::class, 'show'])->middleware('permission:stock.opname')->name('opnames.show');
    Route::post('/stok/opname/{opname}/mulai', [OpnameController::class, 'start'])->middleware('permission:stock.opname')->name('opnames.start');
    Route::post('/stok/opname/{opname}/hitung', [OpnameController::class, 'count'])->middleware('permission:stock.opname')->name('opnames.count');
    Route::post('/stok/opname/{opname}/review', [OpnameController::class, 'review'])->middleware('permission:stock.opname')->name('opnames.review');
    Route::post('/stok/opname/{opname}/setujui', [OpnameController::class, 'approve'])->middleware('permission:stock.opname')->name('opnames.approve');
    Route::post('/stok/opname/{opname}/batal', [OpnameController::class, 'cancel'])->middleware('permission:stock.opname')->name('opnames.cancel');
    Route::get('/stok/label', [LabelController::class, 'index'])->middleware('permission:stock.view')->name('labels.index');
    Route::post('/stok/label/cetak', [LabelController::class, 'print'])->middleware('permission:stock.view')->name('labels.print');
    Route::get('/stok/impor', [InitialStockImportController::class, 'form'])->name('stock.import.form');
    Route::get('/stok/impor/template', [InitialStockImportController::class, 'template'])->name('stock.import.template');
    Route::post('/stok/impor/pratinjau', [InitialStockImportController::class, 'preview'])->name('stock.import.preview');
    Route::post('/stok/impor/komitmen', [InitialStockImportController::class, 'commit'])->name('stock.import.commit');

    // ---- Akuntansi (M.9) ----
    Route::get('/jurnal', [JournalController::class, 'index'])->middleware('permission:journals.view')->name('journals.index');
    Route::get('/jurnal/buat', [JournalController::class, 'create'])->middleware('permission:journals.manual')->name('journals.create');
    Route::post('/jurnal', [JournalController::class, 'store'])->middleware('permission:journals.manual')->name('journals.store');
    Route::get('/buku-besar', [LedgerController::class, 'index'])->middleware('permission:journals.view')->name('ledger.index');
    Route::get('/aset-tetap', [FixedAssetController::class, 'index'])->middleware('permission:assets.manage')->name('assets.index');
    Route::get('/aset-tetap/buat', [FixedAssetController::class, 'create'])->middleware('permission:assets.manage')->name('assets.create');
    Route::post('/aset-tetap', [FixedAssetController::class, 'store'])->middleware('permission:assets.manage')->name('assets.store');
    Route::post('/aset-tetap/penyusutan', [FixedAssetController::class, 'depreciate'])->middleware('permission:assets.manage')->name('assets.depreciate');
    Route::get('/aset-tetap/{asset}', [FixedAssetController::class, 'show'])->middleware('permission:assets.manage')->name('assets.show');
    Route::post('/aset-tetap/{asset}/lepas', [FixedAssetController::class, 'dispose'])->middleware('permission:assets.manage')->name('assets.dispose');
    Route::get('/periode', [PeriodController::class, 'index'])->middleware('permission:periods.lock')->name('periods.index');
    Route::post('/periode/kunci', [PeriodController::class, 'lock'])->middleware('permission:periods.lock')->name('periods.lock');
    Route::post('/periode/buka', [PeriodController::class, 'unlock'])->middleware('permission:periods.unlock')->name('periods.unlock');
    Route::get('/insentif', [IncentiveController::class, 'index'])->middleware('permission:payroll.manage')->name('incentives.index');
    Route::post('/insentif/aturan', [IncentiveController::class, 'storeRule'])->middleware('permission:payroll.manage')->name('incentives.rules.store');
    Route::post('/insentif/aturan/{rule}/toggle', [IncentiveController::class, 'toggleRule'])->middleware('permission:payroll.manage')->name('incentives.rules.toggle');
    Route::post('/insentif/generate', [IncentiveController::class, 'generate'])->middleware('permission:payroll.manage')->name('incentives.generate');
    Route::get('/insentif/{period}', [IncentiveController::class, 'show'])->middleware('permission:payroll.manage')->name('incentives.show');
    Route::get('/insentif/{period}/ekspor', [IncentiveController::class, 'export'])->middleware('permission:payroll.manage')->name('incentives.export');
    Route::post('/insentif/{period}/setujui', [IncentiveController::class, 'approve'])->middleware('permission:payroll.manage')->name('incentives.approve');
    Route::post('/insentif/{period}/bayar', [IncentiveController::class, 'pay'])->middleware('permission:payroll.manage')->name('incentives.pay');
    Route::get('/payroll', [PayrollController::class, 'index'])->middleware('permission:payroll.manage')->name('payroll.index');
    Route::post('/payroll/entri', [PayrollController::class, 'store'])->middleware('permission:payroll.manage')->name('payroll.store');
    Route::post('/payroll/posting', [PayrollController::class, 'post'])->middleware('permission:payroll.manage')->name('payroll.post');
    Route::get('/komisi', [CommissionController::class, 'index'])->middleware('permission:payroll.manage')->name('commissions.index');
    Route::post('/komisi/posting', [CommissionController::class, 'post'])->middleware('permission:payroll.manage')->name('commissions.post');
    Route::post('/komisi/bayar', [CommissionController::class, 'pay'])->middleware('permission:payroll.manage')->name('commissions.pay');
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
    Route::get('/laporan/analitik', [ReportController::class, 'analytics'])->middleware('permission:reports.sales')->name('reports.analytics');
    Route::get('/laporan/neraca', [FinancialReportController::class, 'balanceSheet'])->middleware('permission:reports.financial')->name('financial.balance-sheet');
    Route::get('/laporan/laba-rugi', [FinancialReportController::class, 'incomeStatement'])->middleware('permission:reports.financial')->name('financial.income');
    Route::get('/laporan/arus-kas', [FinancialReportController::class, 'cashFlow'])->middleware('permission:reports.financial')->name('financial.cash-flow');

    // ---- Penjualan (M.3) ----
    Route::get('/penjualan', [SaleController::class, 'index'])->middleware('permission:sales.view')->name('sales.index');
    Route::get('/pelanggan/kredit/{credit}/terapkan', [CustomerCreditController::class, 'applyForm'])->middleware('permission:sales.pay')->name('customer-credits.apply');
    Route::post('/pelanggan/kredit/{credit}/terapkan', [CustomerCreditController::class, 'apply'])->middleware('permission:sales.pay')->name('customer-credits.apply.store');
    Route::get('/penjualan/garansi', [WarrantyController::class, 'index'])->name('warranties.index');
    Route::get('/penjualan/garansi/baru', [WarrantyController::class, 'create'])->middleware('permission:sales.create')->name('warranties.create');
    Route::post('/penjualan/garansi', [WarrantyController::class, 'store'])->middleware('permission:sales.create')->name('warranties.store');
    Route::get('/penjualan/garansi/{warranty}', [WarrantyController::class, 'show'])->name('warranties.show');
    Route::post('/penjualan/garansi/{warranty}/kirim-barang-lama', [WarrantyController::class, 'sendOld'])->middleware('permission:sales.create')->name('warranties.send-old');
    Route::post('/penjualan/garansi/{warranty}/terima-barang-lama', [WarrantyController::class, 'receiveOld'])->middleware('permission:sales.view')->name('warranties.receive-old');
    Route::post('/penjualan/garansi/{warranty}/siapkan-pengganti', [WarrantyController::class, 'prepare'])->middleware('permission:sales.view')->name('warranties.prepare');
    Route::post('/penjualan/garansi/{warranty}/po-pengganti', [WarrantyController::class, 'replacementPo'])->middleware('permission:sales.view')->name('warranties.replacement-po');
    Route::post('/penjualan/garansi/{warranty}/kirim-pengganti', [WarrantyController::class, 'ship'])->middleware('permission:sales.view')->name('warranties.ship');
    Route::post('/penjualan/garansi/{warranty}/terima-pengganti', [WarrantyController::class, 'receiveReplacement'])->middleware('permission:sales.view')->name('warranties.receive-replacement');
    Route::post('/penjualan/garansi/{warranty}/bayar', [WarrantyController::class, 'pay'])->middleware('permission:sales.pay')->name('warranties.pay');
    Route::post('/penjualan/garansi/{warranty}/selesai', [WarrantyController::class, 'complete'])->middleware('permission:sales.create')->name('warranties.complete');
    Route::post('/penjualan/stok-rusak/{brokenStock}/inspeksi', [WarrantyController::class, 'inspect'])->middleware('permission:stock.view')->name('warranties.inspect');
    Route::post('/penjualan/stok-rusak/{brokenStock}/sesuaikan', [WarrantyController::class, 'adjust'])->middleware('permission:stock.view')->name('warranties.adjust');
    Route::get('/penjualan/pengecualian-sinkron', [OfflineExceptionController::class, 'index'])->middleware('permission:sales.view')->name('offline-exceptions.index');
    Route::get('/kasir-offline', [OfflineCashierController::class, 'show'])->middleware('permission:sales.create')->name('offline-cashier.show');
    Route::post('/penjualan/pengecualian-sinkron/{exception}/coba-lagi', [OfflineExceptionController::class, 'retry'])->middleware('permission:sales.create')->name('offline-exceptions.retry');
    Route::post('/penjualan/pengecualian-sinkron/{exception}/buang', [OfflineExceptionController::class, 'discard'])->middleware('permission:sales.exceptions')->name('offline-exceptions.discard');
    Route::get('/penjualan/baru', [SaleController::class, 'create'])->middleware('permission:sales.create')->name('sales.create');
    Route::post('/penjualan', [SaleController::class, 'store'])->middleware('permission:sales.create')->name('sales.store');
    Route::get('/penjualan/cari-produk', [SaleController::class, 'searchProducts'])->middleware('permission:sales.create')->name('sales.search-products');
    Route::get('/penjualan/cari-pelanggan', [SaleController::class, 'searchCustomers'])->middleware('permission:sales.create')->name('sales.search-customers');
    Route::get('/penjualan/pelanggan/{customer}/resep', [SaleController::class, 'customerPrescriptions'])->middleware('permission:sales.create')->name('sales.customer-prescriptions');
    Route::post('/penjualan/{sale}/item/{item}/precal', [SaleController::class, 'updatePrecal'])->middleware('permission:sales.update')->name('sales.precal.update');
    Route::get('/penjualan/{sale}', [SaleController::class, 'show'])->middleware('permission:sales.view')->name('sales.show');
    Route::get('/penjualan/{sale}/bayar', [SaleController::class, 'paymentForm'])->middleware('permission:sales.pay')->name('sales.payment');
    Route::post('/penjualan/{sale}/bayar', [SaleController::class, 'storePayment'])->middleware('permission:sales.pay')->name('sales.payment.store');
    Route::get('/penjualan/{sale}/cicilan', [SaleController::class, 'installmentForm'])->middleware('permission:sales.pay')->name('sales.installment');
    Route::post('/penjualan/{sale}/cicilan', [SaleController::class, 'storeInstallment'])->middleware('permission:sales.pay')->name('sales.installment.store');
    Route::get('/piutang', [ReceivableController::class, 'index'])->middleware('permission:sales.view')->name('receivables.index');
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

    Route::get('/pelanggan/{customer}/fitting', [FittingController::class, 'measure'])->middleware('permission:prescriptions.create')->name('fittings.measure');
    Route::post('/pelanggan/{customer}/fitting', [FittingController::class, 'store'])->middleware('permission:prescriptions.create')->name('fittings.store');
    Route::get('/pelanggan/{customer}/resep/baru', [PrescriptionController::class, 'create'])->middleware('permission:prescriptions.create')->name('prescriptions.create');
    Route::post('/pelanggan/{customer}/resep', [PrescriptionController::class, 'store'])->middleware('permission:prescriptions.create')->name('prescriptions.store');
    Route::get('/resep/{prescription}', [PrescriptionController::class, 'show'])->middleware('permission:prescriptions.view')->name('prescriptions.show');
    Route::get('/resep/{prescription}/ubah', [PrescriptionController::class, 'edit'])->middleware('permission:prescriptions.create')->name('prescriptions.edit');
    Route::put('/resep/{prescription}', [PrescriptionController::class, 'update'])->middleware('permission:prescriptions.create')->name('prescriptions.update');
    Route::post('/resep/{prescription}/duplikat', [PrescriptionController::class, 'duplicate'])->middleware('permission:prescriptions.create')->name('prescriptions.duplicate');
    Route::get('/resep/{prescription}/cetak', [PrescriptionController::class, 'print'])->middleware('permission:prescriptions.print')->name('prescriptions.print');

    // ---- Katalog (M.1) ----
    Route::get('/katalog/{type}/induk/buat', [ProductParentController::class, 'create'])->middleware('permission:products.create')->name('products.parents.create');
    Route::post('/katalog/{type}/induk', [ProductParentController::class, 'store'])->middleware('permission:products.create')->name('products.parents.store');
    Route::get('/produk-induk/{parent}', [ProductParentController::class, 'show'])->middleware('permission:products.view')->name('products.parents.show');
    Route::get('/produk-induk/{parent}/ubah', [ProductParentController::class, 'edit'])->middleware('permission:products.update')->name('products.parents.edit');
    Route::put('/produk-induk/{parent}', [ProductParentController::class, 'update'])->middleware('permission:products.update')->name('products.parents.update');
    Route::post('/produk-induk/{parent}/alih-aktif', [ProductParentController::class, 'toggleActive'])->middleware('permission:products.deactivate')->name('products.parents.toggle-active');
    Route::get('/katalog/{type}', [ProductController::class, 'index'])->middleware('permission:products.view')->name('products.index');
    Route::get('/katalog/{type}/buat', [ProductController::class, 'create'])->middleware('permission:products.create')->name('products.create');
    Route::post('/katalog/{type}', [ProductController::class, 'store'])->middleware('permission:products.create')->name('products.store');
    Route::get('/produk/{product}', [ProductController::class, 'show'])->middleware('permission:products.view')->name('products.show');
    Route::get('/produk/{product}/ubah', [ProductController::class, 'edit'])->middleware('permission:products.update')->name('products.edit');
    Route::put('/produk/{product}', [ProductController::class, 'update'])->middleware('permission:products.update')->name('products.update');
    Route::post('/produk/{product}/alih-aktif', [ProductController::class, 'toggleActive'])->middleware('permission:products.deactivate')->name('products.toggle-active');

    // ---- Membership & Poin (F3.6) ----
    Route::get('/member', [MemberController::class, 'index'])->middleware('permission:members.view')->name('members.index');
    Route::get('/member/follow-up', [MemberController::class, 'followUp'])->middleware('permission:members.view')->name('members.follow-up');
    Route::get('/member/level', [MembershipLevelController::class, 'index'])->middleware('permission:members.manage')->name('members.levels');
    Route::post('/member/level', [MembershipLevelController::class, 'store'])->middleware('permission:members.manage')->name('members.levels.store');
    Route::put('/member/level/{level}', [MembershipLevelController::class, 'update'])->middleware('permission:members.manage')->name('members.levels.update');
    Route::post('/member/level/{level}/alih-aktif', [MembershipLevelController::class, 'toggleActive'])->middleware('permission:members.manage')->name('members.levels.toggle');
    Route::put('/member/pengaturan-poin', [MembershipLevelController::class, 'updateSettings'])->middleware('permission:members.manage')->name('members.points-settings');
    Route::get('/member/{customer}/sesuaikan', [MemberController::class, 'adjustForm'])->middleware('permission:points.adjust')->name('members.adjust');
    Route::post('/member/{customer}/sesuaikan', [MemberController::class, 'storeAdjust'])->middleware('permission:points.adjust')->name('members.adjust.store');
    Route::post('/member/{customer}/portal', [MemberController::class, 'issuePortalToken'])->middleware('permission:members.manage')->name('members.portal.issue');
    Route::post('/member/{customer}/portal/cabut', [MemberController::class, 'revokePortalToken'])->middleware('permission:members.manage')->name('members.portal.revoke');

    // ---- Inbox & Kampanye WhatsApp (F3.8) ----
    Route::get('/wa/inbox', [WaInboxController::class, 'index'])->middleware('permission:wa.inbox')->name('wa-inbox.index');
    Route::get('/wa/inbox/{customer}', [WaInboxController::class, 'show'])->middleware('permission:wa.inbox')->name('wa-inbox.show');
    Route::post('/wa/inbox/{customer}/balas', [WaInboxController::class, 'reply'])->middleware('permission:wa.inbox')->name('wa-inbox.reply');
    Route::get('/wa/kampanye', [WaCampaignController::class, 'index'])->middleware('permission:wa.campaigns')->name('wa-campaigns.index');
    Route::post('/wa/kampanye', [WaCampaignController::class, 'store'])->middleware('permission:wa.campaigns')->name('wa-campaigns.store');
    Route::get('/wa/kampanye/{campaign}', [WaCampaignController::class, 'show'])->middleware('permission:wa.campaigns')->name('wa-campaigns.show');
    Route::post('/wa/kampanye/{campaign}/setujui', [WaCampaignController::class, 'approve'])->middleware('permission:wa.campaigns.approve')->name('wa-campaigns.approve');
    Route::post('/wa/kampanye/{campaign}/kirim', [WaCampaignController::class, 'send'])->middleware('permission:wa.campaigns')->name('wa-campaigns.send');
    Route::post('/wa/kampanye/{campaign}/batalkan', [WaCampaignController::class, 'cancel'])->middleware('permission:wa.campaigns')->name('wa-campaigns.cancel');
    Route::post('/wa/template/{template}/setujui', [WaTemplateController::class, 'approve'])->middleware('permission:wa.campaigns.approve')->name('wa-templates.approve');

    // ---- Komunikasi WhatsApp (F3.4) ----
    Route::get('/pengaturan/umum', [GeneralSettingsController::class, 'edit'])->middleware('permission:settings.manage')->name('settings.general.edit');
    Route::put('/pengaturan/umum', [GeneralSettingsController::class, 'update'])->middleware('permission:settings.manage')->name('settings.general.update');
    Route::get('/pengaturan/wa', [WaGatewayController::class, 'edit'])->middleware('permission:settings.manage')->name('wa-gateway.edit');
    Route::put('/pengaturan/wa', [WaGatewayController::class, 'update'])->middleware('permission:settings.manage')->name('wa-gateway.update');
    Route::post('/pengaturan/wa/uji', [WaGatewayController::class, 'test'])->middleware('permission:settings.manage')->name('wa-gateway.test');
    Route::get('/pengaturan/token-api', [ApiTokenController::class, 'index'])->middleware('permission:api_tokens.manage')->name('api-tokens.index');
    Route::post('/pengaturan/token-api', [ApiTokenController::class, 'store'])->middleware('permission:api_tokens.manage')->name('api-tokens.store');
    Route::post('/pengaturan/token-api/{token}/cabut', [ApiTokenController::class, 'revoke'])->middleware('permission:api_tokens.manage')->name('api-tokens.revoke');
    Route::get('/pengaturan/backup', [BackupController::class, 'index'])->middleware('permission:settings.manage')->name('backups.index');
    Route::post('/pengaturan/backup', [BackupController::class, 'store'])->middleware('permission:settings.manage')->name('backups.store');
    Route::get('/pengaturan/backup/unduh', [BackupController::class, 'download'])->middleware('permission:settings.manage')->name('backups.download');
    Route::post('/pengaturan/backup/hapus', [BackupController::class, 'destroy'])->middleware('permission:settings.manage')->name('backups.destroy');
    Route::post('/pengaturan/backup/pulihkan', [BackupController::class, 'restore'])->middleware('permission:settings.manage')->name('backups.restore');
    Route::get('/pengaturan/log-aktivitas', [AuditLogController::class, 'index'])->middleware('permission:settings.manage')->name('audit-logs.index');
    Route::get('/pengaturan/log-aktivitas/{log}', [AuditLogController::class, 'show'])->middleware('permission:settings.manage')->name('audit-logs.show');
    Route::get('/pengaturan/ai', [AiSettingController::class, 'edit'])->middleware('permission:settings.manage')->name('ai-setting.edit');
    Route::put('/pengaturan/ai', [AiSettingController::class, 'update'])->middleware('permission:settings.manage')->name('ai-setting.update');
    Route::get('/asisten', [AssistantController::class, 'index'])->name('assistant.index');
    Route::post('/asisten/tanya', [AssistantController::class, 'ask'])->name('assistant.ask');
    Route::get('/pengaturan/perangkat-offline', [OfflineDeviceController::class, 'index'])->middleware('permission:api_tokens.manage')->name('offline-devices.index');
    Route::post('/pengaturan/perangkat-offline', [OfflineDeviceController::class, 'store'])->middleware('permission:api_tokens.manage')->name('offline-devices.store');
    Route::post('/pengaturan/perangkat-offline/{device}/cabut', [OfflineDeviceController::class, 'revoke'])->middleware('permission:api_tokens.manage')->name('offline-devices.revoke');
    Route::get('/wa/template', [WaTemplateController::class, 'index'])->middleware('permission:wa.templates')->name('wa-templates.index');
    Route::get('/wa/template/{template}/ubah', [WaTemplateController::class, 'edit'])->middleware('permission:wa.templates')->name('wa-templates.edit');
    Route::put('/wa/template/{template}', [WaTemplateController::class, 'update'])->middleware('permission:wa.templates')->name('wa-templates.update');
    Route::get('/wa/log', [WaLogController::class, 'index'])->middleware('permission:wa.send')->name('wa-log.index');

    Route::middleware('permission:branches.manage')->group(function () {
        Route::get('/cabang', [BranchController::class, 'index'])->name('branches.index');
        Route::get('/cabang/buat', [BranchController::class, 'create'])->name('branches.create');
        Route::post('/cabang', [BranchController::class, 'store'])->name('branches.store');
        Route::get('/cabang/{branch}', [BranchController::class, 'show'])->name('branches.show');
        Route::get('/cabang/{branch}/ubah', [BranchController::class, 'edit'])->name('branches.edit');
        Route::put('/cabang/{branch}', [BranchController::class, 'update'])->name('branches.update');
        Route::post('/cabang/{branch}/alih-aktif', [BranchController::class, 'toggleActive'])->name('branches.toggle-active');
    });

    Route::middleware('permission:insurances.manage')->group(function () {
        Route::get('/asuransi', [InsuranceController::class, 'index'])->name('insurances.index');
        Route::get('/asuransi/buat', [InsuranceController::class, 'create'])->name('insurances.create');
        Route::post('/asuransi', [InsuranceController::class, 'store'])->name('insurances.store');
        Route::get('/asuransi/{insurance}/ubah', [InsuranceController::class, 'edit'])->name('insurances.edit');
        Route::put('/asuransi/{insurance}', [InsuranceController::class, 'update'])->name('insurances.update');
        Route::post('/asuransi/{insurance}/alih-aktif', [InsuranceController::class, 'toggleActive'])->name('insurances.toggle-active');
    });

    Route::middleware('permission:doctors.manage')->group(function () {
        Route::get('/dokter', [DoctorController::class, 'index'])->name('doctors.index');
        Route::get('/dokter/buat', [DoctorController::class, 'create'])->name('doctors.create');
        Route::post('/dokter', [DoctorController::class, 'store'])->name('doctors.store');
        Route::get('/dokter/{doctor}', [DoctorController::class, 'show'])->name('doctors.show');
        Route::get('/dokter/{doctor}/ubah', [DoctorController::class, 'edit'])->name('doctors.edit');
        Route::put('/dokter/{doctor}', [DoctorController::class, 'update'])->name('doctors.update');
        Route::post('/dokter/{doctor}/alih-aktif', [DoctorController::class, 'toggleActive'])->name('doctors.toggle-active');
    });

    Route::middleware('permission:suppliers.manage')->group(function () {
        Route::get('/supplier', [SupplierController::class, 'index'])->name('suppliers.index');
        Route::get('/supplier/buat', [SupplierController::class, 'create'])->name('suppliers.create');
        Route::post('/supplier', [SupplierController::class, 'store'])->name('suppliers.store');
        Route::get('/supplier/{supplier}', [SupplierController::class, 'show'])->name('suppliers.show');
        Route::get('/supplier/{supplier}/ubah', [SupplierController::class, 'edit'])->name('suppliers.edit');
        Route::put('/supplier/{supplier}', [SupplierController::class, 'update'])->name('suppliers.update');
        Route::post('/supplier/{supplier}/alih-aktif', [SupplierController::class, 'toggleActive'])->name('suppliers.toggle-active');
    });

    Route::get('/laporan/pemakaian-asuransi', [ReportController::class, 'insuranceUsages'])->middleware('permission:reports.sales')->name('reports.insurance-usages');
});

// Webhook Meta (publik, tanpa sesi/CSRF — dijaga verify_token & ID pesan).
Route::get('/wa/webhook', [WaWebhookController::class, 'verify'])->name('wa-webhook.verify');
Route::post('/wa/webhook', [WaWebhookController::class, 'handle'])
    ->withoutMiddleware([ValidateCsrfToken::class])
    ->name('wa-webhook.handle');

// Portal member (F3.7): PUBLIK ber-token — token adalah kredensialnya;
// hash tersimpan di member_portal_tokens, dapat dicabut dari menu Member.
Route::get('/m/{token}', [MemberPortalController::class, 'show'])
    ->middleware('throttle:30,1')
    ->name('portal.show');

require __DIR__.'/auth.php';
