# Operator notes: 00.07.00

Use this unreleased version in isolated development. No customer funds, sensitive verification documents or public restricted content. README contains the initial setup commands; versioned iteration documents provide account/MFA/media examples. No production deployment or novice onboarding acceptance is implied.

## Configuration

APP_NAME is escaped display text, for example Jane's Studio. APP_ENV=local permits loopback development; testing is for disposable tests. APP_DEBUG stays false. APP_URL is the canonical origin, locally http://localhost:8000; enabled account email requires HTTPS outside local loopback. Generate APP_KEY once, keep it private and back it up securely. Never replace an existing key or use the public CI/PHPUnit fixture key for a real installation.

DB_CONNECTION=sqlite uses database/database.sqlite; a custom DB_DATABASE is an absolute private path. PostgreSQL uses DB_CONNECTION=pgsql plus DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME and DB_PASSWORD. For remote PostgreSQL, use DB_SSLMODE=verify-full and appropriately configured CA/server trust. CI database credentials are disposable examples only.

SESSION_DRIVER=file is the local supported path; array is test-only. SESSION_SECURE_COOKIE=false is for loopback HTTP only. Cookies are host-only, HTTP-only and SameSite=Lax. CMS_SESSION_IDLE_MINUTES defaults 30 (allowed 5-120); CMS_SESSION_ABSOLUTE_MINUTES defaults 720 (idle through 1440). Shortening limits can end existing sessions. Multi-node deployment is not accepted. CACHE_STORE=file is local persistent framework cache; critical write limits now use transactional SQL attempt budgets, not filesystem counters. Broad reverse-proxy trust is not configured.

LOG_CHANNEL=daily and LOG_LEVEL=info produce creator-local JSON with fourteen-day rotation. Only known events and approved identifiers survive redaction. Exception classes/messages/traces are not diagnostic output. Early bootstrap fallback logs only a constant event. Infrastructure access logs must separately omit sensitive query strings and control retention. Hosted metrics and alerts are not implemented.

CMS_PAYMENTS_ENABLED=false and CMS_RESTRICTED_PUBLISHING_ENABLED=false are false-only reserved switches. Enabling them fails startup. TEST quotes do not require or use wallet private keys. Never supply a seed phrase.

CMS_ACCOUNT_MAIL_ENABLED defaults false. Opt in only after creator SMTP, sender and database queue configuration. Production-style SMTP requires implicit TLS (MAIL_SCHEME=smtps, normally port465); plain SMTP is local-loopback-only. No mail-to-log transport is supported. Account jobs are encrypted with APP_KEY, and raw failed-job persistence is disabled. Full settings and examples: iterations/00.05.00.md.

## Media preview

CMS_MEDIA_ENABLED=false and CMS_MEDIA_VIDEO_ENABLED=false are the defaults. Images require GD JPEG/PNG/WebP support and a private writable storage/app/private/media tree. Video additionally requires compatible FFmpeg/FFprobe at CMS_FFMPEG_PATH and CMS_FFPROBE_PATH (default /usr/bin/ffmpeg and /usr/bin/ffprobe). CMS_MEDIA_QUOTA_MB defaults 2048 and permits 64-1048576. Details: iterations/00.07.00.md.

Keep source and derivatives outside the web document root. Never create a public symlink into private storage. Images are limited to 8 MiB/20million pixels; MP4 to 64 MiB/10 minutes. Reservation includes maximum derivative capacity, so free disk space alone does not determine upload acceptance. Sources remain private while queued, processing or failed. Only ready derivatives can be delivered through current authorization.

Use a distinct media worker, not the short-timeout mail worker:

```sh
php -d memory_limit=512M artisan queue:work media --queue=media --sleep=1 --tries=1 --timeout=240 --memory=512
php artisan queue:work database --queue=account-mail --sleep=1 --tries=3 --timeout=30
php artisan cms:media-status
# Daily security-metadata maintenance:
php artisan cms:security-prune
# Hourly account-token maintenance:
php artisan auth:clear-resets
```

Media reservation retry_after is 300 seconds, greater than the 240-second worker timeout. Supervise workers and restart after code/configuration updates. Native decoder isolation, patched image/tool assessments and resource-limit enforcement at the operating-system level remain required before production. A separate queue alone is not isolation.

The studio shows queued/processing/failed/deleting states and reserved quota. Remove failed items to release capacity only after file removal succeeds. A stalled-operation warning is not authorization to edit ledger values or kill live work. Reviewed crash recovery and full observability remain unfinished.

## Upgrade, backup and rollback

Apply migrations before starting new code. Preserve APP_KEY and current password/MFA state. Existing pre-registry sessions require sign-in, and administrator studio access requires MFA. Schedule maintenance and stop workers before schema changes. Clear/rebuild cached configuration, routes and views after changes.

Development backups must consistently capture database, private media and private configuration/key on creator-controlled storage outside public/. Test restores into a separate restricted installation. Automated encrypted backup/restore is not yet implemented. Do not restore obsolete passwords, revoked sessions or consumed recovery codes, and account for removed media being resurrected by stale backups.

The first migration drops core tables on rollback. New media rollback drops only media metadata/ledger and deliberately leaves files; blindly reapplying it would lose quota accounting. Do not run destructive rollback against needed data. Prefer a forward fix. Any emergency code downgrade needs maintenance mode, reviewed access restrictions, session invalidation and a compatible state plan. Never reopen pre-MFA code as an unreviewed recovery shortcut. Earlier commits remain available, not automatically safe public downgrade targets.

## Diagnostics and release

Run cms:doctor after migrations without printing secrets. /up is liveness only, not dependency readiness. Use cms:media-status for counts/quota/stalls. A 503 at /checkout is intentional. Run all tests only against disposable databases. Passing scoped checks does not complete independent security, deployment-image, secret-history, browser/accessibility, load, provider or product acceptance.

Keep the release gate closed for every unresolved issue above Low, unknown severity, absent/failed/stale/wrong-candidate evidence and incomplete mandatory review. Only an approved exact commit may later receive its matching release tag and artifacts. No release is authorized by these operator instructions.
