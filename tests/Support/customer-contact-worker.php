<?php

use App\Models\User;
use App\Services\CustomerContactService;
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
    app(CustomerContactService::class)->update(User::findOrFail((int) $argv[2]), User::findOrFail((int) $argv[3]), [
        'name' => $argv[4], 'phone' => '019-876 5432', 'profile_version' => 1, 'reason' => 'Concurrent customer contact QA',
    ]);
    echo json_encode(['result' => 'saved'], JSON_THROW_ON_ERROR);
} catch (ValidationException) {
    echo json_encode(['result' => 'conflict'], JSON_THROW_ON_ERROR);
}
