<?php

use App\Models\Payment;
use App\Services\PaymentVerificationService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$connection = json_decode(file_get_contents($argv[1]), true, flags: JSON_THROW_ON_ERROR);
if (! preg_match('/^lr_yazid_test_[a-f0-9]{16}$/', $connection['database'])) {
    exit(1);
}
config(['database.default' => 'mysql', 'database.connections.mysql' => $connection]);
DB::purge('mysql');
$payment = Payment::findOrFail((int) $argv[2]);
Http::preventStrayRequests();
Http::fake(['*/getBillTransactions' => Http::response([[
    'billExternalReferenceNo' => $payment->reference, 'billpaymentInvoiceNo' => 'TPTESTCONCURRENT',
    'billpaymentStatus' => '1',
    'billpaymentAmount' => intdiv($payment->amount_sen, 100).'.'.sprintf('%02d', $payment->amount_sen % 100),
]])]);
echo "READY\n";
flush();
echo json_encode(['result' => app(PaymentVerificationService::class)->reconcile($payment)], JSON_THROW_ON_ERROR);
