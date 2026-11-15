<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Appointment;
use App\Models\AppointmentRescheduleRequest;
use App\Models\AppointmentService;
use App\Models\MembershipPlan;
use App\Models\User;
use App\Services\AppointmentBookingService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PDO;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class MySqlAppointmentConcurrencyTest extends TestCase
{
    public static function races(): array
    {
        return [
            'duplicate submission' => ['duplicate'],
            'overlapping services for same user' => ['conflict'],
            'last approved place' => ['approval'],
            'duplicate approval' => ['repeat_approval'],
            'last reschedule place' => ['move'],
        ];
    }

    #[DataProvider('races')]
    public function test_independent_processes_preserve_appointment_invariants(string $race): void
    {
        if (getenv('MYSQL_CONCURRENCY_TEST') !== '1') {
            $this->markTestSkipped('Set MYSQL_CONCURRENCY_TEST=1 to test real MySQL locking in a temporary database.');
        }
        $connection = config('database.connections.mysql');
        $database = 'lr_yazid_test_'.bin2hex(random_bytes(8));
        $server = new PDO("mysql:host={$connection['host']};port={$connection['port']};charset=utf8mb4",
            $connection['username'], $connection['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $server->exec("CREATE DATABASE `$database` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $file = tempnam(storage_path('framework/testing'), 'appointment-');
        $processes = [];
        try {
            $connection['database'] = $database;
            file_put_contents($file, json_encode($connection, JSON_THROW_ON_ERROR));
            config(['database.default' => 'mysql', 'database.connections.mysql' => $connection]);
            DB::purge('mysql');
            $this->artisan('migrate', ['--force' => true])->assertSuccessful();
            $admin = User::factory()->create();
            $admin->role = UserRole::Admin;
            $admin->save();
            $plan = MembershipPlan::create(['name' => 'VIP', 'price_sen' => 19900, 'duration_months' => 12]);
            $users = [];
            for ($index = 0; $index < 2; $index++) {
                $user = User::factory()->create();
                $user->memberships()->create(['membership_plan_id' => $plan->id, 'status' => 'active', 'starts_at' => now()->subDay(), 'expires_at' => now()->addYear()]);
                $users[] = $user;
            }
            $service = AppointmentService::create(['name' => 'Consultation', 'location' => 'QA venue']);
            $slot = $service->slots()->create(['created_by' => $admin->id, 'starts_at' => now()->addDays(3), 'ends_at' => now()->addDays(3)->addHour(), 'capacity' => 1, 'location' => 'QA venue']);
            $booking = app(AppointmentBookingService::class);
            $key = (string) Str::uuid();
            $commands = [];
            $originals = [];
            for ($index = 0; $index < 2; $index++) {
                $target = $slot;
                if ($race === 'conflict' && $index === 1) {
                    $other = AppointmentService::create(['name' => 'Other service', 'location' => 'QA venue']);
                    $target = $other->slots()->create(['created_by' => $admin->id, 'starts_at' => $slot->starts_at, 'ends_at' => $slot->ends_at, 'capacity' => 1, 'location' => 'QA venue']);
                }
                if (in_array($race, ['duplicate', 'conflict'], true)) {
                    $commands[] = ['book', $users[0]->id, $target->id, $race === 'duplicate' ? $key : (string) Str::uuid()];

                    continue;
                }
                if ($race === 'repeat_approval' && $index === 1) {
                    $commands[] = $commands[0];

                    continue;
                }
                if ($race === 'move') {
                    $target = $service->slots()->create(['created_by' => $admin->id, 'starts_at' => now()->addDays(5 + $index),
                        'ends_at' => now()->addDays(5 + $index)->addHour(), 'capacity' => 1, 'location' => 'Original venue']);
                }
                $appointment = $booking->book($users[$index], $target, ['booking_key' => (string) Str::uuid(), 'purpose' => 'Concurrent QA request']);
                if ($race === 'move') {
                    $booking->review($appointment, $admin, 'approved', null);
                    $booking->requestReschedule($appointment, $slot, $users[$index], 'Request earlier appointment');
                    $request = $appointment->rescheduleRequests()->firstOrFail();
                    $commands[] = ['move', $request->id, $slot->id, $key];
                    $originals[] = $target->id;
                } else {
                    $commands[] = ['approve', $appointment->id, $target->id, $key];
                }
            }
            DB::beginTransaction();
            if ($race === 'conflict') {
                User::whereKey($users[0]->id)->lockForUpdate()->firstOrFail();
            } else {
                AppointmentService::whereKey($service->id)->lockForUpdate()->firstOrFail();
            }
            foreach ($commands as [$mode, $id, $slotId, $bookingKey]) {
                $process = new Process([PHP_BINARY, base_path('tests/Support/appointment-worker.php'), $file, $mode, (string) $id, (string) $slotId, $bookingKey, (string) $admin->id], base_path(), ['APP_ENV' => 'testing']);
                $process->setTimeout(30);
                $process->start();
                $processes[] = $process;
            }
            foreach ($processes as $process) {
                $this->assertTrue($process->waitUntil(fn ($type, $output) => str_contains($output, 'READY')));
            }
            DB::commit();
            $results = [];
            foreach ($processes as $process) {
                $process->wait();
                $this->assertTrue($process->isSuccessful(), $process->getErrorOutput().$process->getOutput());
                $output = substr($process->getOutput(), strpos($process->getOutput(), '{'));
                $results[] = json_decode($output, true, flags: JSON_THROW_ON_ERROR)['result'];
            }
            sort($results);
            $this->assertSame(match ($race) {
                'duplicate' => ['pending', 'pending'], 'conflict' => ['pending', 'rejected'], 'repeat_approval' => ['approved', 'approved'],
                default => ['approved', 'rejected'],
            }, $results);
            $this->assertSame(in_array($race, ['duplicate', 'conflict', 'repeat_approval'], true) ? 1 : 2, Appointment::count());
            $this->assertSame(in_array($race, ['duplicate', 'conflict'], true) ? 0 : 1, Appointment::where('appointment_slot_id', $slot->id)->where('status', 'approved')->count());
            if ($race === 'move') {
                $this->assertSame(1, AppointmentRescheduleRequest::where('status', 'approved')->count());
                $this->assertSame(1, AppointmentRescheduleRequest::where('status', 'pending')->count());
                $this->assertSame(1, Appointment::whereIn('appointment_slot_id', $originals)->where('status', 'approved')->count());
            }
            if ($race === 'repeat_approval') {
                $this->assertSame(1, DB::table('appointment_status_histories')->where('event', 'approved')->count());
            }
        } finally {
            foreach ($processes as $process) {
                if ($process->isRunning()) {
                    $process->stop();
                }
            }
            while (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            DB::purge('mysql');
            $server->exec("DROP DATABASE `$database`");
            if (is_file($file)) {
                unlink($file);
            }
        }
    }
}
