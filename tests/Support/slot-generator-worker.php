<?php

use App\Models\AppointmentService;
use App\Services\AppointmentSchedulingService;
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
$service = AppointmentService::findOrFail((int) $argv[2]);
$date = now()->addDay()->toDateString();
try {
    if ($argv[4] === 'manual') {
        app(AppointmentSchedulingService::class)->saveSlot($service, null, [
            'starts_at' => $date.' 09:15:00', 'ends_at' => $date.' 09:45:00',
            'capacity' => 1, 'location' => 'QA venue', 'status' => 'available',
        ], (int) $argv[3]);
        $result = ['result' => 'manual'];
    } else {
        $result = app(AppointmentSchedulingService::class)->generate($service, [
            'from' => $date, 'to' => $date, 'weekdays' => [1, 2, 3, 4, 5, 6, 7],
            'day_start' => '09:00', 'day_end' => '10:00', 'duration_minutes' => 30,
            'capacity' => 1, 'location' => 'QA venue', 'preview' => false,
        ], (int) $argv[3]);
        $result['result'] = 'generated';
    }
    echo json_encode($result, JSON_THROW_ON_ERROR);
} catch (ValidationException) {
    echo json_encode(['result' => 'rejected'], JSON_THROW_ON_ERROR);
}
