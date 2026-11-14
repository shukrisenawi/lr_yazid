<?php

namespace Tests\Feature;

use App\Models\Membership;
use App\Models\MembershipPlan;
use App\Models\ProgramNoticeDelivery;
use App\Models\User;
use App\Models\VipProgram;
use App\Models\VipProgramRegistration;
use Illuminate\Support\Facades\DB;
use PDO;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class MySqlProgramConcurrencyTest extends TestCase
{
    public static function registrationRaces(): array
    {
        return ['last place' => [false], 'duplicate submission' => [true]];
    }

    #[DataProvider('registrationRaces')]
    public function test_independent_processes_preserve_program_capacity(bool $sameUser): void
    {
        if (getenv('MYSQL_CONCURRENCY_TEST') !== '1') {
            $this->markTestSkipped('Set MYSQL_CONCURRENCY_TEST=1 to test real MySQL locking in a temporary database.');
        }
        $connection = config('database.connections.mysql');
        $database = 'lr_yazid_test_'.bin2hex(random_bytes(8));
        $server = new PDO("mysql:host={$connection['host']};port={$connection['port']};charset=utf8mb4",
            $connection['username'], $connection['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $server->exec("CREATE DATABASE `$database` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $file = tempnam(storage_path('framework/testing'), 'program-');
        $processes = [];
        try {
            $connection['database'] = $database;
            file_put_contents($file, json_encode($connection, JSON_THROW_ON_ERROR));
            config(['database.default' => 'mysql', 'database.connections.mysql' => $connection]);
            DB::purge('mysql');
            $this->artisan('migrate', ['--force' => true])->assertSuccessful();
            $program = VipProgram::create([
                'title' => 'Last place', 'slug' => 'last-place', 'starts_at' => now()->addWeek(), 'ends_at' => now()->addWeek()->addHour(),
                'registration_opens_at' => now()->subDay(), 'registration_closes_at' => now()->addDays(6),
                'capacity' => 1, 'status' => 'published', 'location' => 'Test venue',
            ]);
            $plan = MembershipPlan::create(['name' => 'VIP', 'price_sen' => 19900, 'duration_months' => 12]);
            $users = [];
            for ($index = 0; $index < 2; $index++) {
                if ($sameUser && $index === 1) {
                    $users[] = $users[0];

                    continue;
                }
                $user = User::factory()->create();
                Membership::create(['user_id' => $user->id, 'membership_plan_id' => $plan->id, 'status' => 'active',
                    'starts_at' => now()->subDay(), 'expires_at' => now()->addYear()]);
                $users[] = $user->id;
            }
            DB::beginTransaction();
            VipProgram::whereKey($program->id)->lockForUpdate()->firstOrFail();
            foreach ($users as $userId) {
                $process = new Process([PHP_BINARY, base_path('tests/Support/program-worker.php'), $file, (string) $userId, (string) $program->id], base_path(), ['APP_ENV' => 'testing']);
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
            $this->assertSame($sameUser ? ['registered', 'registered'] : ['registered', 'rejected'], $results);
            $this->assertSame(1, VipProgramRegistration::where('status', 'registered')->count());
            $this->assertSame(1, VipProgramRegistration::firstOrFail()->version);
            $this->assertSame(1, ProgramNoticeDelivery::count());
            $this->assertSame(2, DB::table('jobs')->count());
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
