# TwitchyButt CMS

Version **00.03.00**. A self-hosted, content-neutral creator CMS foundation. Development preview, not a production platform.

Each creator operates their own application and database. There is no central content hosting. The intended supported payment flow allocates a 2% licensing fee. This release calculates immutable **TEST invoice quotes only**. It accepts no real funds, has no wallet connection, and never treats a quote as a payment.

## Run a local preview

Requires 64-bit PHP 8.3 or later within the supported framework range, Composer 2, and PHP PDO SQLite, mbstring, XML, DOM, ctype, fileinfo and OpenSSL extensions. PostgreSQL additionally requires pdo_pgsql. Dependency versions are fixed by composer.lock. Use a separate development machine or isolated environment.

```sh
git clone --branch build/00.03.00 https://github.com/paulkakell/twitchybutt.git
cd twitchybutt
cp .env.example .env
php scripts/prepare.php
composer install
php artisan key:generate
php artisan migrate
php artisan cms:admin --email=creator@example.com
php artisan cms:doctor
php artisan serve --host=127.0.0.1 --port=8000
```

The administrator command prompts for a new password and confirmation. It refuses an existing email; it does not silently promote an existing member. No default administrator or password is shipped. Keep `.env`, the database, session files and logs private. The development server is not a production deployment recommendation. Only `public/` may ever be a web document root.

## Features and examples

- **Accounts:** visit `/register` to create a member; `/login` and `/logout` use session authentication. Members cannot access `/studio`. Email verification, password recovery and MFA remain unimplemented.
- **Publishing:** in `/studio/posts/new`, write a general post, choose `published`, and set price `0` for open access. Drafts are not publicly visible. Edit any owned installation post through the studio. Plain text is escaped; HTML and media uploads are not supported.
- **Classification:** `general`, `restricted`, or `unclassified`. For example, a restricted draft can be reviewed privately by the administrator. Restricted/unclassified publication is rejected server-side, including when a client forges the form. These are workflow labels, not legal exemptions.
- **Paid-post protection:** setting `20` creates a locked post priced at 20 TEST. Only a valid, unrevoked, unexpired entitlement permits a member to read it. This release has no public or administrator entitlement-grant endpoint. Tests create fixtures directly; they are not payment evidence.
- **Test invoices:** signed-in members can create a quote for a published general paid post. The server snapshots 20.000000 total, 0.400000 fee and 19.600000 creator allocation. Client-supplied prices/fees are ignored. Duplicate idempotency keys return the same snapshot for the same buyer and post; reuse for another post is rejected.
- **Reports:** `/report` accepts text reports without an account. Only administrators can read `/studio/reports`. It is an intake prototype, not a complete statutory notice-and-removal system.
- **Diagnostics:** `/up` is liveness only; `php artisan cms:doctor` checks configuration and database connectivity without printing secrets. Logs are structured JSON with IDs, not post bodies or passwords.

## Configuration

See `.env.example` and `docs/OPERATIONS.md`. `CMS_PAYMENTS_ENABLED` and `CMS_RESTRICTED_PUBLISHING_ENABLED` are reserved, false-only flags: setting either true prevents startup. Neither flag can activate absent integrations. Production mode rejects debugging or insecure session cookies but this does not certify production readiness.

## Validation

```sh
composer validate --strict
composer audit --locked
vendor/bin/pint --test
vendor/bin/phpstan analyse --memory-limit=1G
vendor/bin/phpunit
python3 scripts/security_scan.py
```

GitHub Actions installs from the lock in fresh SQLite and PostgreSQL environments, runs syntax/style/type checks, dependency audit, forward/rollback migrations, unit/integration/regression checks, cached build checks, real-HTTP CSRF checks and a small performance smoke check. The first-commit-only lock bootstrap has narrowly scoped repository write permission on `build/00.03.00`; validation jobs remain read-only. Check the actual run conclusion, not the presence of a workflow file.

## Not implemented or approved

Real or testnet blockchain transactions, contract deployment/audit, crypto network/token selection, treasury address, recurring billing, refunds, tax calculation, media hosting/transcoding, performer verification, viewer age assurance, statutory case handling, live streaming, email delivery, password recovery, MFA, production installation/upgrades/backups and final commercial license terms. Do not accept customer money or publish restricted material using this preview.

See `CHANGELOG.md`, `docs/ARCHITECTURE.md`, `docs/OPERATIONS.md`, `docs/SECURITY.md`, and `docs/RELEASE_00.03.00.md`. Tracking: issue #1. Main is preserved for review via a pull request. No release tag is created until review and release gates are complete.

Copyright remains with the project owner. Public repository visibility does not grant an open-source or commercial production-use license. Third-party packages retain their own licenses; inventory them with `composer licenses`.
