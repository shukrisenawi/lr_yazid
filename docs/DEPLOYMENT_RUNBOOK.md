# Production deployment and recovery runbook

This is an operator checklist, not evidence that production deployment, a backup,
mail delivery or a ToyyibPay payment has occurred. Linux/Supervisor examples below
must be adapted and tested for the actual host. Local XAMPP is development only.

## Release gates

1. Review the requirements/status document, production data, legal content and
   real invoice issuer. Never import the development/QA database into production.
2. Run backend/static/frontend tests, the production build, `composer audit` and
   `npm audit --omit=dev`. Run the opt-in MySQL tests on disposable databases only.
3. Complete a genuine Sandbox payment/callback/reconciliation matrix on a separate
   public HTTPS deployment before considering live collection. HTTP fakes and
   synthetic database records are not provider acceptance.
4. Prove web/CLI PHP mail delivery, worker/scheduler supervision, backup restoration
   and production performance/security on the actual deployment.
5. Run `php artisan app:check-deployment` as the same OS user/PHP binary as the
   worker. `--json` emits stable check IDs without credential values or private
   job payloads. Exit 1 means automatic checks failed; exit 0 means only that those
   checks passed. **Neither result waives the separately listed manual acceptance.**

The checker is read-only: no migrations, queue processing, provider requests,
test mail, writes to settings, cache clearing or backup creation. Opening a DB
connection and checking schema/configuration does not establish operational health.
The application must be able to bootstrap to run the command; diagnose earlier
PHP/configuration startup failures privately rather than publishing stack traces.
Expect local development to fail production checks. Disabled collection and
invoice issuance intentionally remain failures until the approved launch stage;
do not enable them merely to obtain a green report.

## Host and secret configuration

- Serve only the application's `public/` directory. Do not expose project files,
  `.env`, private storage, database dumps or logs through the web server.
- Use supported PHP/MySQL/MariaDB versions and matching DB client tools. Install
  the Composer-required extensions, GD/WebP, OpenSSL, Fileinfo and CLI worker support.
- Set `APP_ENV=production`, `APP_DEBUG=false`, a real canonical HTTPS `APP_URL`,
  `SESSION_DRIVER=database`, `SESSION_ENCRYPT=true`, `SESSION_SECURE_COOKIE=true`,
  `SESSION_HTTP_ONLY=true`, `SESSION_SAME_SITE=lax`, `SESSION_PATH=/` and
  `SESSION_DOMAIN=null` (host-only). Lax supports provider returns.
- Provision a dedicated password-protected application DB account; do not use
  root. Review its actual grants separately. Use distinct least-privilege accounts
  for backups, restore drills and any schema deployment privileges.
- Keep a stable `APP_KEY` in the host secret manager, with protected historical
  keys needed by `APP_PREVIOUS_KEYS`. Generate the initial key once, not on each
  release. Losing/changing keys can make gateway snapshots and settings unreadable.
- Use `MAIL_MAILER=mail` with a real authorized sender. Configure PHP `mail()` in
  server `php.ini`, for **both** web PHP and worker/CLI PHP. No application SMTP
  credentials are needed. Set appropriate OS-level transport timeouts; successful
  PHP hand-off alone is not proof of recipient-inbox delivery.
- Use a shared persistent cache/lock store. Database queue jobs must use the exact
  business connection name (`DB_QUEUE_CONNECTION` unset or equal to `DB_CONNECTION`).
  Keep the failed-job store in that DB. Workers must listen to both queues below.
- Give the actual PHP/worker OS users access to `storage/` and `bootstrap/cache`.
  Do not grant world-writable permissions to the source tree.

### TLS termination and canonical origin

Production requests must be HTTPS and match the host **and port** in `APP_URL`.
The app returns 400 for other origins instead of generating login/reset/payment
links from an untrusted Host. Redirect HTTP and alternate hostnames at the edge,
before reaching PHP. Configure health probes with the canonical Host/origin too.

Behind a TLS-terminating reverse proxy, set `TRUSTED_PROXIES` to its explicit IPs
or bounded CIDRs, separated by commas. Blank trusts none. Hostnames, wildcards,
`REMOTE_ADDR`, malformed networks and `/0` fail closed. A valid list still needs an
operator review of network ownership; configuration screening cannot prove that.

The proxy must strip client-supplied forwarding headers and set client IP,
protocol and port correctly. Preserve the real canonical `Host` header.
`X-Forwarded-Host` is deliberately not trusted. There is no automatic trust-all
fallback for a hosting-platform-looking hostname. Do not fix a 400 by trusting
every address; correct the proxy/Host/protocol/port configuration.

### Response and upload policy

Application responses, including Inertia HTML/JSON, redirects and handled errors,
use `private, no-store` because even public pages include personalized auth/cart
props. They include nosniff, frame denial, no-referrer and restricted camera,
microphone and geolocation permissions. Preserve these at the CDN/reverse proxy;
do not edge-cache authenticated pages, invoices, carts or callback responses.

The CSP is a **baseline** (`base-uri`, `object-src`, `frame-ancestors`), not a
complete script-source XSS policy. Existing stronger application policies are
preserved. A stricter nonce-based policy requires separate editor, Inertia,
appearance, passkey and provider-flow testing; never add `unsafe-eval` to silence errors.

HSTS is sent only for secure production requests, for 180 days, without
`includeSubDomains` or preload. Do not assume control of other subdomains.
Disable PHP exposure/version banners and review edge TLS/certificate configuration.

Hashed `/build/assets/` files may use long-lived immutable static caching, and
static raster images may be cached separately. These bypass PHP, so the operator
must set their headers at the web server. Do not apply application no-store to
hashed assets or apply a blanket public cache rule to dynamic responses.

Run `php artisan storage:link` where appropriate. The link must resolve exactly
to `storage/app/public`, not all of storage. Never execute uploaded content.
For example, adapt and validate this Nginx location alongside the real site/PHP
configuration (the return-only conditional is intentional):

```nginx
location ^~ /storage/ {
    if ($uri !~* "\.(jpe?g|png|webp)$") { return 404; }
    try_files $uri =404;
    add_header X-Content-Type-Options nosniff always;
    add_header Content-Security-Policy "default-src 'none'; sandbox" always;
}
```

This whitelist matches the current raster-only upload pipeline. Review equivalent
Apache/IIS rules on other hosts. Verify that `.php`, `.phtml`, `.phar`, HTML, SVG,
dotfiles, double extensions and missing paths cannot execute or expose content.
Validate server configuration before reload; this snippet is not a complete vhost.

## Controlled release

Use an atomic release directory/symlink strategy and a tested maintenance window.
Keep shared storage and secrets outside replaceable release folders.

1. Verify a recent recoverable encrypted backup and its matching key escrow.
2. Disable new collection as needed, notify operators, pause scheduled writers and
   drain/stop workers gracefully before incompatible schema/code changes.
3. Enter maintenance mode (`php artisan down`) if required. Callbacks may receive
   503 while unavailable; verify provider retries and reconcile afterward.
   Never treat browser returns as payment settlement or discard callback history.
4. Install locked dependencies with `composer install --no-dev --optimize-autoloader`.
   Supply the host's secret/configuration values without putting them in Git.
5. Run reviewed `php artisan migrate --force`, then build assets using the locked
   Node dependencies (`npm ci`, `npm run build`). Wayfinder generation may need
   access to the migrated schema; do not point CI at an uncontrolled live DB.
6. Run `php artisan optimize`, reload PHP-FPM/OPcache as appropriate, and restart
   long-running workers so they use the new code/configuration.
7. Run the read-only deployment check, complete manual acceptance, restore cron/
   worker supervision and leave maintenance mode (`php artisan up`) only when safe.
8. Confirm `/up`, protected routes, both queues, scheduled tasks, logs and alerts.
   `/up` proves the HTTP app can boot; it is not a DB/queue/provider/mail readiness check.

Do not roll back financial migrations blindly or restore an older DB over newer
payments. Prefer a compatible code rollback or reviewed forward fix. A restore
requires a separate incident plan accounting for provider transactions, bills,
membership periods, reservations, appointments and notification replay.

## Workers and scheduler

Linux Supervisor reference (adjust paths/user/log rotation):

```ini
[program:yazid-worker]
command=/usr/bin/php /var/www/yazid/current/artisan queue:work database --queue=commerce-notices,default --tries=3 --backoff=30 --sleep=3 --timeout=60 --max-time=3600
directory=/var/www/yazid/current
user=www-data
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
stopwaitsecs=120
redirect_stderr=true
stdout_logfile=/var/log/yazid/worker.log
```

Keep `retry_after` at least 90 seconds and worker/job timeouts **shorter** than it.
Set external mail/network timeouts below the worker limit. Test graceful shutdown
and restart. `php artisan queue:restart` relies on the configured cache; an actual
process supervisor must restart exited workers. Do not use a browser request or
an unmonitored terminal as the production worker.

Install cron as the application OS user, using the same PHP/configuration:

```cron
* * * * * cd /var/www/yazid/current && /usr/bin/php artisan schedule:run >> /var/log/yazid/scheduler.log 2>&1
```

Monitor successful execution of reservation expiry (minute), payment reconciliation
(five minutes), membership expiry/reminders (hourly), and appointment reminders
(five minutes). Existing overlap locks require working persistent shared storage.
Review multi-node scheduling explicitly; do not assume a lock configuration is
proof that the right process ran. Investigate stuck jobs/locks before clearing them.

Alert on failed jobs, growing queue depth/age on **either** queue, missing scheduler
executions, failed reconciliation, disk space and DB/mail/HTTP errors. Inspect
`queue:failed` privately. Retry specific reviewed failures, not a blind replay of
every historical notice. Mail is at-least-once; a crash after server acceptance can
lead to a duplicate external email even with application delivery tracking.

## Encrypted backup and restore drill

No remote backup destination, schedule, credentials or retention policy is
installed by the repository. Assign an owner, RPO/RTO, alerting, off-host encrypted
destination and retention policy. Back up the full business DB and uploaded assets,
and escrow the matching current/previous application keys separately.

Use a root/service-owned mode-0600 DB client options file outside the repository
for backup credentials, not a password on the command line. Restrict the backup
account to the required read/metadata privileges. Coordinate DB/uploads capture
when writers are paused, or document and test an equivalent consistency strategy.

Linux example for a single InnoDB application DB, using a matching MariaDB/MySQL
client and `age` public-key encryption. The recipient is public; its private key
must be separately protected. All variables below must be supplied by the operator:

```bash
set -euo pipefail
umask 077
: "${BACKUP_DIR:?set a private backup directory}"
: "${DB_NAME:?set the application database name}"
: "${BACKUP_AGE_RECIPIENT:?set the reviewed encryption recipient}"
[[ "$DB_NAME" =~ ^[A-Za-z0-9_]+$ ]] || { echo "Invalid database name" >&2; exit 1; }
stamp="$(date -u +%Y%m%dT%H%M%SZ)"
mkdir -p -- "$BACKUP_DIR"
snapshot_dir="$(mktemp -d "$BACKUP_DIR/$stamp.XXXXXX")"
tmp="$snapshot_dir/database.sql.age.part"
trap 'rm -f -- "$tmp"' EXIT
mariadb-dump --defaults-extra-file=/etc/yazid/backup.cnf \
  --single-transaction --quick --routines --triggers --events --hex-blob "$DB_NAME" \
  | age --recipient "$BACKUP_AGE_RECIPIENT" > "$tmp"
mv -- "$tmp" "$snapshot_dir/database.sql.age"
trap - EXIT
(cd -- "$snapshot_dir" && sha256sum database.sql.age > database.sql.age.sha256)
```

Do not use `--databases` in this example: it can add source-database selection
statements that undermine an isolated restore target. Validate privileges, storage
engine assumptions and SQL routine/event behavior for the actual server version.
Encrypt/upload assets separately, verify checksums and off-host arrival, and alert
on command/pipeline failures. A completed dump alone is not a successful backup.
Keep partial/failed artifacts private and never place backups beneath `public/`.

Restore drills must use an **isolated host/account unable to access production**:

```bash
set -euo pipefail
age --decrypt --identity /secure/offline/restore-key.txt /private/backup.sql.age \
  | mariadb --defaults-extra-file=/etc/yazid/isolated-restore.cnf --database="${RESTORE_DB:?set the isolated database}"
```

Verify the checksum before decryption. The target must be deliberately provisioned
and verified in advance. Do not start
the restored site's queue workers, scheduler or public HTTP service before a safety
review. Block outbound payment/mail traffic and use a safe local mailer in the
isolated environment. **`TOYYIBPAY_ENABLED=false` alone is insufficient:** saved
database settings override environment defaults, and payment rows retain encrypted
credential snapshots. Do not let restored production jobs send notices or payments.

Verify schema/migrations, key decryption without printing secrets, counts and
relationships for orders/items/payments/refunds/memberships/appointments/audits,
immutable invoice snapshots, image files and access-control behavior. Review
provider reconciliation and queued-notice replay before any recovery promotion.
Record backup timestamp/checksum, operator, isolated destination, elapsed recovery
time and pass/fail evidence in a private operations system. Retain backups only
according to the approved policy; never delete the last verified recoverable copy.

## Final acceptance still required

- External HTTPS/Host/proxy/cookie/static-upload checks, not just local tests.
- Genuine Sandbox and separately approved Production gateway acceptance.
- Real PHP mail receipt, sender authorization and bounce/failure handling.
- Demonstrated supervised queue/scheduler recovery and failure alerts.
- A verified off-host encrypted backup **and** isolated restore drill.
- Real seller/legal content, deployment data review and production performance.

Do not advertise the store as production-ready until those checks have evidence.
