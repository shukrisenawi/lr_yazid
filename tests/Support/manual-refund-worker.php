<?php

use App\Models\Order;
use App\Models\User;
use App\Services\ManualRefundService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

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
try {
    app(ManualRefundService::class)->record(Order::findOrFail((int) $argv[2]), User::findOrFail((int) $argv[3]),
        json_decode($argv[4], true, flags: JSON_THROW_ON_ERROR));
    echo json_encode(['result' => 'recorded'], JSON_THROW_ON_ERROR);
} catch (ValidationException) {
    echo json_encode(['result' => 'rejected'], JSON_THROW_ON_ERROR);
}
