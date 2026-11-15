<?php

use App\Models\Order;
use App\Models\User;
use App\Services\InvoiceService;
use App\Services\OrderFulfillmentService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$connection = json_decode(file_get_contents($argv[1]), true, flags: JSON_THROW_ON_ERROR);
if (! preg_match('/^lr_yazid_test_[a-f0-9]{16}$/', $connection['database'])) {
    exit(1);
}
config(['database.default' => 'mysql', 'database.connections.mysql' => $connection]);
DB::purge('mysql');
echo "READY\n";
flush();
$order = Order::findOrFail((int) $argv[2]);
$admin = User::findOrFail((int) $argv[3]);
if ($argv[4] === 'invoice') {
    $result = app(InvoiceService::class)->issue($order, $admin)->reference;
} else {
    app(OrderFulfillmentService::class)->update($order, $admin, ['fulfillment_status' => 'shipped', 'courier_name' => 'QA courier', 'tracking_number' => 'QA123', 'reason' => 'Concurrent QA dispatch']);
    $result = 'shipped';
}
echo json_encode(['result' => $result], JSON_THROW_ON_ERROR);
