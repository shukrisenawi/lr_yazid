<?php

use App\Models\Appointment;
use App\Models\AppointmentRescheduleRequest;
use App\Models\AppointmentSlot;
use App\Models\User;
use App\Services\AppointmentBookingService;
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
    $service = app(AppointmentBookingService::class);
    if ($argv[2] === 'book') {
        $service->book(User::findOrFail((int) $argv[3]), AppointmentSlot::findOrFail((int) $argv[4]),
            ['booking_key' => $argv[5], 'purpose' => 'Concurrent QA request']);
        $result = 'pending';
    } elseif ($argv[2] === 'move') {
        $service->reviewReschedule(AppointmentRescheduleRequest::findOrFail((int) $argv[3]), User::findOrFail((int) $argv[6]), true, null);
        $result = 'approved';
    } else {
        $service->review(Appointment::findOrFail((int) $argv[3]), User::findOrFail((int) $argv[6]), 'approved', null);
        $result = 'approved';
    }
    echo json_encode(['result' => $result], JSON_THROW_ON_ERROR);
} catch (ValidationException) {
    echo json_encode(['result' => 'rejected'], JSON_THROW_ON_ERROR);
}
