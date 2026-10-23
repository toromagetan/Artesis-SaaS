<?php

/**
 * Pekerja konkurensi (bukan test): dijalankan sebagai proses PHP
 * terpisah oleh ConcurrencyGateTest. Menunggu berkas "go", lalu
 * mengeksekusi SATU aksi skenario melawan database bersama, dan
 * menulis hasil JSON (ok / kelas pengecualian) ke berkas hasil.
 *
 * Argumen: worker.php <skenario> <params.json> <ready-file> <go-file> <result-file>
 */
use App\Modules\Accounting\Models\Journal;
use App\Modules\Accounting\Services\PostingService;
use App\Modules\Identity\Models\User;
use App\Modules\Lab\Models\LensProcess;
use App\Modules\Lab\Services\LensProcessService;
use App\Modules\Master\Models\Supplier;
use App\Modules\Purchasing\Models\PurchaseOrder;
use App\Modules\Purchasing\Models\PurchaseRequest;
use App\Modules\Purchasing\Services\CollectivePaymentService;
use App\Modules\Purchasing\Services\PurchaseRequestService;
use App\Modules\Purchasing\Services\ReceiptService;
use App\Modules\Sales\Models\Sale;
use App\Modules\Sales\Services\SalesService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

[$script, $scenario, $paramsFile, $readyFile, $goFile, $resultFile] = $argv;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$params = json_decode((string) file_get_contents($paramsFile), true);
$owner = User::where('username', 'owner')->firstOrFail();

// Siap → tunggu aba-aba serentak.
file_put_contents($readyFile, 'ready');
$deadline = microtime(true) + 30;
while (! file_exists($goFile)) {
    if (microtime(true) > $deadline) {
        file_put_contents($resultFile, json_encode(['ok' => false, 'error' => 'timeout-menunggu-go']));
        exit(1);
    }
    usleep(5000);
}

$result = ['ok' => false, 'error' => 'tidak-dijalankan'];
try {
    DB::connection()->reconnect();
    match ($scenario) {
        'pay' => app(SalesService::class)->addPayment(
            $owner, Sale::withoutGlobalScopes()->findOrFail($params['sale_id']), 'cash', $params['amount'],
        ),
        'collective' => app(CollectivePaymentService::class)->process(
            $owner,
            Supplier::findOrFail($params['supplier_id']),
            $params['amount'], 'transfer', now()->format('Y-m-d'),
        ),
        'sale' => app(SalesService::class)->createSale($owner, [
            'customer_id' => $params['customer_id'],
            'branch_id' => $params['branch_id'],
            'sale_date' => now()->format('Y-m-d'),
            'tax_mode' => 'inclusive',
        ], [
            ['product_id' => $params['product_id'], 'qty' => 1, 'unit_price' => $params['price']],
        ], [['method' => 'cash', 'amount' => $params['price']]]),
        'complete' => app(LensProcessService::class)->complete(
            $owner, LensProcess::findOrFail($params['process_id']),
        ),
        'receive' => app(ReceiptService::class)->store(
            $owner,
            PurchaseOrder::withoutGlobalScopes()->findOrFail($params['po_id']),
            [['purchase_order_item_id' => $params['po_item_id'], 'qty' => (int) $params['qty']]],
        ),
        'submit' => app(PurchaseRequestService::class)->submit(
            $owner, PurchaseRequest::withoutGlobalScopes()->findOrFail($params['pr_id']),
        ),
        // Balapan LNT-002: pekerja -0 memposting, pekerja -1 mengunci
        // periode yang sama, dilepas serentak.
        'postlock' => (function () use ($params, $owner, $readyFile) {
            $posting = app(PostingService::class);
            if (str_ends_with($readyFile, '-0')) {
                return $posting->post('race', null, $params['date'], [
                    ['account' => '1-1100', 'debit' => '1000'],
                    ['account' => '4-1100', 'credit' => '1000'],
                ], 'uji balapan LNT-002', $owner);
            }

            return $posting->lockPeriod($owner, $params['period']);
        })(),
        // Balapan LNT-005: dua pekerja membalik jurnal yang sama.
        'reverse' => app(PostingService::class)->reverse(
            Journal::findOrFail($params['journal_id']), 'uji balapan LNT-005', $owner,
        ),
        default => throw new RuntimeException('Skenario tidak dikenal: '.$scenario),
    };
    $result = ['ok' => true];
} catch (Throwable $e) {
    $result = ['ok' => false, 'error' => $e::class];
}
file_put_contents($resultFile, json_encode($result));
