<?php

namespace App\Services;

use App\Models\InvoiceSetting;
use App\Models\PaymentGatewaySetting;
use App\Models\User;
use App\Support\PublicHttpsUrl;
use App\Support\TrustedProxyList;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class DeploymentReadinessService
{
    /** @var list<array{id: string, status: string, message: string}> */
    private array $checks = [];

    /** @return array{automatic_checks_passed: bool, failures: int, warnings: int, checks: list<array{id: string, status: string, message: string}>, manual_acceptance_required: list<string>} */
    public function report(): array
    {
        $this->checks = [];
        $this->check('production_environment', app()->isProduction(), 'Run the release with APP_ENV=production.');
        $this->check('debug_disabled', config('app.debug') === false, 'APP_DEBUG must be false.');
        $this->check('public_https_url', PublicHttpsUrl::configured((string) config('app.url')), 'APP_URL must be a public HTTPS base URL; reachability is not verified.');
        $this->check('malaysia_timezone', config('app.timezone') === 'Asia/Kuala_Lumpur', 'Use Asia/Kuala_Lumpur for business dates.');
        $key = (string) config('app.key');
        $key = str_starts_with($key, 'base64:') ? base64_decode(substr($key, 7), true) : $key;
        $this->check('encryption_key', is_string($key) && Encrypter::supported($key, config('app.cipher')), 'A valid application encryption key must be configured and securely backed up.');
        $this->check('session_protection', config('session.driver') === 'database' && config('session.encrypt') === true
            && config('session.secure') === true && config('session.http_only') === true && config('session.same_site') === 'lax'
            && config('session.domain') === null && config('session.path') === '/',
            'Use host-only encrypted database sessions with Secure, HttpOnly, path / and SameSite=Lax cookies.');
        $this->check('proxy_allowlist', TrustedProxyList::parse((string) config('security.trusted_proxies', ''))['valid'],
            'Proxy trust must be blank or explicit IPs/CIDRs; wildcards, hostnames and /0 are rejected.');
        $this->check('transactional_queue', config('queue.default') === 'database' && config('queue.connections.database.driver') === 'database'
            && in_array(config('queue.connections.database.connection'), [null, config('database.default')], true),
            'The database queue must share the exact business connection; workers need commerce-notices,default.');
        $this->check('queue_retry_window', (int) config('queue.connections.database.retry_after') >= 90,
            'Keep retry_after at least 90 seconds; the supervised worker timeout must be shorter.');
        $this->check('failed_job_storage', in_array(config('queue.failed.driver'), ['database', 'database-uuids'], true)
            && in_array(config('queue.failed.database'), [null, config('database.default')], true),
            'Failed jobs must be retained in the configured business database for private operator review.');
        $cache = config('cache.stores.'.config('cache.default').'.driver');
        $this->check('shared_cache', in_array($cache, ['database', 'redis', 'memcached'], true),
            'Use a persistent shared cache/lock store; actual scheduler and lock operation require acceptance.');
        $mailer = config('mail.default');
        $this->check('php_mail_transport', $mailer === 'mail' && config('mail.mailers.'.$mailer.'.transport') === 'mail' && function_exists('mail'),
            'Use MAIL_MAILER=mail with PHP mail() available; this does not prove server hand-off or delivery.');
        $from = (string) config('mail.from.address');
        $domain = strtolower((string) substr(strrchr($from, '@') ?: '', 1));
        $this->check('sender_address', filter_var($from, FILTER_VALIDATE_EMAIL) !== false
            && ! in_array($domain, ['', 'example.com', 'example.org', 'example.net', 'localhost'], true)
            && ! preg_match('/\.example\.(com|org|net)$/', $domain)
            && ! preg_match('/\.(test|invalid|local)$/', $domain), 'Configure a real sender address authorized by the deployment mail service.');
        $this->check('built_assets', is_file(public_path('build/manifest.json')) && is_file(public_path('build/fonts-manifest.json'))
            && ! is_file(public_path('hot')), 'Deploy production assets and remove development hot-server markers.');
        $this->check('runtime_writable', is_writable(storage_path('framework')) && is_writable(storage_path('logs')) && is_writable(base_path('bootstrap/cache')),
            'Storage/framework, storage/logs and bootstrap/cache must be writable by the actual PHP/worker OS user.');
        $this->check('public_upload_link', is_dir(public_path('storage'))
            && realpath(public_path('storage')) === realpath(storage_path('app/public')), 'public/storage must resolve to storage/app/public; disable executable uploads in the web server.');
        $this->check('public_upload_disk', config('filesystems.disks.public.driver') === 'local',
            'The current image-processing implementation requires the local public disk.');
        $this->check('configuration_cached', app()->configurationIsCached(), 'Cache production configuration after setting environment values; restart long-running workers.');
        $this->databaseChecks();
        $failures = count(array_filter($this->checks, fn ($check) => $check['status'] === 'fail'));
        $warnings = count(array_filter($this->checks, fn ($check) => $check['status'] === 'warning'));

        return ['automatic_checks_passed' => $failures === 0, 'failures' => $failures, 'warnings' => $warnings, 'checks' => $this->checks,
            'manual_acceptance_required' => [
                'Verify public TLS, forwarded-header handling, document root, static-asset headers and non-executable uploads from outside the host.',
                'Prove scheduler execution, supervised worker operation on both queues, failure alerts and recovery after restart.',
                'Perform genuine ToyyibPay Sandbox callback/reconciliation acceptance, then review Production activation separately.',
                'Prove PHP mail() hand-off and recipient-inbox delivery on both web and worker PHP configurations.',
                'Restore an encrypted database/uploads backup with the correct application keys into an isolated environment and verify it.',
                'Review seller/legal content, remove development fixtures from the release dataset, and complete production performance/security acceptance.',
            ]];
    }

    private function databaseChecks(): void
    {
        try {
            $connection = DB::connection();
            $connection->getPdo();
            $this->check('database_connection', true, 'Business database connection opened; this is not a write/restore test.');
            $this->check('mysql_database', $connection->getDriverName() === 'mysql', 'Production business storage must use the supported MySQL/MariaDB driver.');
            $this->check('database_account', ! in_array(strtolower((string) $connection->getConfig('username')), ['', 'root'], true)
                && filled($connection->getConfig('password')), 'Use a dedicated password-protected database account; least privilege must be reviewed separately.');
            $migrator = app(Migrator::class);
            $migrated = $migrator->repositoryExists()
                && array_diff(array_keys($migrator->getMigrationFiles(database_path('migrations'))), $migrator->getRepository()->getRan()) === [];
            $this->check('migrations_current', $migrated, 'All application migrations must be recorded; this command never runs migrations.');
            $this->check('runtime_tables', Schema::hasTable(config('queue.connections.database.table', 'jobs'))
                && Schema::connection(config('session.connection'))->hasTable(config('session.table', 'sessions'))
                && Schema::hasTable(config('queue.failed.table', 'failed_jobs')),
                'Required queue, session and failed-job tables must exist on their configured connections.');
            $cache = config('cache.stores.'.config('cache.default'));
            if (is_array($cache) && ($cache['driver'] ?? null) === 'database') {
                $this->check('cache_tables', Schema::connection($cache['connection'] ?? null)->hasTable($cache['table'] ?? 'cache')
                    && Schema::connection($cache['lock_connection'] ?? $cache['connection'] ?? null)->hasTable($cache['lock_table'] ?? 'cache_locks'),
                    'Database cache and lock tables must exist; distributed locking still needs operational acceptance.');
            }
            if ($migrated && Schema::hasTable(config('queue.failed.table', 'failed_jobs'))) {
                $failed = DB::table(config('queue.failed.table', 'failed_jobs'))->count();
                $this->checks[] = ['id' => 'failed_job_records', 'status' => $failed === 0 ? 'pass' : 'warning',
                    'message' => $failed === 0 ? 'No failed-job records found; worker liveness is not proven.' : 'Failed-job records exist. Inspect privately and resolve; payloads are not included in this report.'];
            }
            $this->check('verified_administrator', User::where('role', 'admin')->whereNotNull('email_verified_at')->exists(),
                'Provision a verified administrator securely; credential strength, MFA and account ownership still require review.');
            $settings = PaymentGatewaySetting::current(); // Read-only, including the environment fallback.
            $this->check('payment_collection', $settings->enabled && $settings->configured(), 'Collection requires selected-environment credentials. Keep disabled until external payment acceptance is complete.');
            $this->checks[] = ['id' => 'payment_environment', 'status' => $settings->environment === 'production' ? 'pass' : 'warning',
                'message' => $settings->environment === 'production' ? 'Production environment selected; provider validity is not verified.' : 'Sandbox selected. Test payments are not live collection.'];
            $issuer = InvoiceSetting::find(1); // Do not call current(): it would create a row.
            $this->check('invoice_issuer', $issuer !== null && $issuer->enabled && filled($issuer->seller_name)
                && filled($issuer->seller_address) && filter_var($issuer->seller_email, FILTER_VALIDATE_EMAIL) !== false,
                'Enable invoices only with real seller details; legal/tax validity is not verified here.');
        } catch (Throwable) {
            // Never print database DSNs, credentials, decrypt failures, stack traces or private records.
            $this->check('database_checks', false, 'Database-dependent checks could not finish. Inspect connection, schema and encrypted settings privately.');
        }
    }

    private function check(string $id, bool $passed, string $message): void
    {
        $this->checks[] = ['id' => $id, 'status' => $passed ? 'pass' : 'fail', 'message' => $message];
    }
}
