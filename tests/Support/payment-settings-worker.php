<?php

use App\Models\User;
use App\Services\PaymentSettingsService;
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
config(['database.default' => 'mysql', 'database.connections.mysql' => $connection,
    'toyyibpay' => ['enabled' => false, 'environment' => 'sandbox', 'sandbox_secret_key' => '',
        'sandbox_category_code' => '', 'production_secret_key' => '', 'production_category_code' => '']]);
DB::purge('mysql');
echo "READY\n";
flush();
if (trim(fgets(STDIN)) !== 'GO') {
    exit(1);
}
try {
    app(PaymentSettingsService::class)->update(User::findOrFail((int) $argv[2]), [
        'enabled' => false, 'environment' => 'sandbox', 'sandbox_category_code' => 'QATEST',
        'production_category_code' => null, 'sandbox_secret_key' => 'isolated-settings-key-'.$argv[4],
        'production_secret_key' => '', 'clear_sandbox_secret_key' => false, 'clear_production_secret_key' => false,
        'version' => (int) $argv[3],
    ]);
    $result = 'saved';
} catch (ValidationException) {
    $result = 'stale';
}
echo json_encode(['result' => $result], JSON_THROW_ON_ERROR);
