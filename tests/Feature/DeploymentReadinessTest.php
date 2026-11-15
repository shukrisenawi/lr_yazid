<?php

namespace Tests\Feature;

use App\Models\PaymentGatewaySetting;
use App\Services\DeploymentReadinessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class DeploymentReadinessTest extends TestCase
{
    use RefreshDatabase;

    private function statuses(): array
    {
        return collect(app(DeploymentReadinessService::class)->report()['checks'])->pluck('status', 'id')->all();
    }

    public function test_local_json_report_is_failure_without_mutations_or_provider_requests_or_secrets(): void
    {
        Http::preventStrayRequests();
        config(['app.debug' => true, 'app.url' => 'http://localhost:8000', 'mail.default' => 'log',
            'toyyibpay.enabled' => false, 'toyyibpay.sandbox_secret_key' => 'PRIVATE-NOT-REAL-PROVIDER-KEY']);
        $exit = Artisan::call('app:check-deployment', ['--json' => true]);
        $output = Artisan::output();
        $report = json_decode($output, true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame(1, $exit);
        $this->assertFalse($report['automatic_checks_passed']);
        $this->assertGreaterThan(0, $report['failures']);
        $this->assertCount(6, $report['manual_acceptance_required']);
        $this->assertStringNotContainsString('PRIVATE-NOT-REAL-PROVIDER-KEY', $output);
        $this->assertStringNotContainsString(config('app.key'), $output);
        foreach (['invoice_settings', 'payment_gateway_settings', 'website_settings', 'users', 'jobs'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
        Http::assertNothingSent();
    }

    public function test_automatic_configuration_checks_do_not_treat_proxy_wildcards_or_split_queues_as_ready(): void
    {
        config(['security.trusted_proxies' => '*', 'queue.connections.database.connection' => 'another_connection',
            'session.secure' => false, 'session.encrypt' => false, 'queue.connections.database.retry_after' => 30]);
        $checks = $this->statuses();
        foreach (['proxy_allowlist', 'transactional_queue', 'session_protection', 'queue_retry_window'] as $id) {
            $this->assertSame('fail', $checks[$id]);
        }
    }

    public function test_valid_config_fields_pass_screening_without_proving_delivery_or_provider_validity(): void
    {
        config(['app.debug' => false, 'app.url' => 'https://shop.example.com', 'session.driver' => 'database',
            'session.secure' => true, 'session.encrypt' => true, 'session.http_only' => true, 'session.same_site' => 'lax',
            'security.trusted_proxies' => '10.1.2.3/32', 'mail.default' => 'mail', 'mail.from.address' => 'private-sender@shop.company.my']);
        PaymentGatewaySetting::create(['id' => 1, 'environment' => 'sandbox', 'enabled' => true,
            'sandbox_secret_key' => 'NOT-A-REAL-PRIVATE-KEY', 'sandbox_category_code' => 'NOT-A-REAL-CATEGORY']);
        $checks = $this->statuses();
        foreach (['debug_disabled', 'public_https_url', 'session_protection', 'proxy_allowlist', 'sender_address', 'payment_collection', 'migrations_current'] as $id) {
            $this->assertSame('pass', $checks[$id]);
        }
        $this->assertSame('warning', $checks['payment_environment']);
        $this->assertSame('fail', $checks['mysql_database']);
        $this->assertStringNotContainsString('NOT-A-REAL-PRIVATE-KEY', json_encode(app(DeploymentReadinessService::class)->report()));
        config(['session.domain' => '.company.my']);
        $this->assertSame('fail', $this->statuses()['session_protection']);
    }

    public function test_invalid_key_and_placeholder_sender_and_missing_url_fail_safely(): void
    {
        config(['app.key' => 'base64:PRIVATE-INVALID-KEY', 'app.url' => null, 'mail.from.address' => 'hello@sub.example.com']);
        $checks = $this->statuses();
        foreach (['encryption_key', 'public_https_url', 'sender_address'] as $id) {
            $this->assertSame('fail', $checks[$id]);
        }
        $this->assertStringNotContainsString('PRIVATE-INVALID-KEY', json_encode(app(DeploymentReadinessService::class)->report()));
    }

    public function test_pending_migrations_are_detected_but_never_run(): void
    {
        $migration = DB::table('migrations')->latest('id')->firstOrFail();
        DB::table('migrations')->where('id', $migration->id)->delete();
        $before = DB::table('migrations')->count();
        $this->assertSame('fail', $this->statuses()['migrations_current']);
        $this->assertSame($before, DB::table('migrations')->count());
    }

    public function test_failed_job_payloads_are_not_printed_and_remain_untouched(): void
    {
        DB::table('failed_jobs')->insert(['uuid' => (string) Str::uuid(), 'connection' => 'database', 'queue' => 'default',
            'payload' => 'PRIVATE-JOB-PAYLOAD', 'exception' => 'PRIVATE-STACK-TRACE', 'failed_at' => now()]);
        $report = app(DeploymentReadinessService::class)->report();
        $checks = collect($report['checks'])->pluck('status', 'id');
        $this->assertSame('warning', $checks['failed_job_records']);
        $this->assertStringNotContainsString('PRIVATE-JOB-PAYLOAD', json_encode($report));
        $this->assertStringNotContainsString('PRIVATE-STACK-TRACE', json_encode($report));
        $this->assertDatabaseCount('failed_jobs', 1);
    }

    public function test_database_exception_text_cannot_leak_connection_credentials(): void
    {
        $original = DB::getFacadeRoot();
        try {
            DB::shouldReceive('connection')->once()->andThrow(new RuntimeException('PRIVATE-DSN-PASSWORD'));
            $report = app(DeploymentReadinessService::class)->report();
            $this->assertFalse($report['automatic_checks_passed']);
            $this->assertStringNotContainsString('PRIVATE-DSN-PASSWORD', json_encode($report));
            $this->assertSame('fail', collect($report['checks'])->pluck('status', 'id')['database_checks']);
        } finally {
            DB::swap($original);
        }
    }

    public function test_unreadable_gateway_credentials_fail_without_printing_or_repairing_them(): void
    {
        DB::table('payment_gateway_settings')->insert(['id' => 1, 'enabled' => true, 'environment' => 'sandbox',
            'sandbox_secret_key' => 'PRIVATE-INVALID-CIPHERTEXT', 'sandbox_category_code' => 'QA-CATEGORY']);
        $report = app(DeploymentReadinessService::class)->report();
        $this->assertFalse($report['automatic_checks_passed']);
        $this->assertSame('fail', collect($report['checks'])->pluck('status', 'id')['database_checks']);
        $this->assertStringNotContainsString('PRIVATE-INVALID-CIPHERTEXT', json_encode($report));
        $this->assertSame('PRIVATE-INVALID-CIPHERTEXT', DB::table('payment_gateway_settings')->value('sandbox_secret_key'));
    }

    public function test_success_exit_still_prints_manual_acceptance_not_go_live_approval(): void
    {
        $this->mock(DeploymentReadinessService::class, function ($mock) {
            $mock->shouldReceive('report')->once()->andReturn(['automatic_checks_passed' => true, 'failures' => 0, 'warnings' => 0,
                'checks' => [['id' => 'fixture', 'status' => 'pass', 'message' => 'Synthetic command-output fixture']],
                'manual_acceptance_required' => ['External acceptance remains required.']]);
        });
        $this->artisan('app:check-deployment')->expectsOutputToContain('not go-live approval')
            ->expectsOutputToContain('External acceptance remains required.')->assertSuccessful();
    }
}
