# TwitchyButt CMS

Version **00.05.00**. Self-hosted, content-neutral creator software. **Unreleased development preview; release approval is blocked.** This iteration adds account email and recovery on top of 00.04.00. It does not complete the full account/security milestone or enable payments.

## Product roadmap

[Read the roadmap baseline](ROADMAP.md), [edit the live planning Sheet](https://docs.google.com/spreadsheets/d/1bdbGDfaQMY68vkWfJhkeO3uBuDx-qHyqr8Vjjp1av_A/edit), or [read this iteration's scope, examples and rollback](docs/iterations/00.05.00.md). The versioned TSV remains the dated 00.03.01 baseline; live updates are reconciled manually, not automatically synchronized. Feature versions are actual construction identities; proposed milestone versions are subject to replanning.

Creators own their application, database, mail service, sessions and future media storage. No central content hosting exists. The intended crypto flow allocates 2% to licensing; current invoices are **TEST quotes only**. No wallet is connected, no funds are accepted, and quotes do not grant access.

## Run an isolated local preview

Use 64-bit PHP 8.3+, Composer 2 and the locked package extensions, including PDO SQLite, mbstring, XML/DOM, ctype, fileinfo and OpenSSL. PostgreSQL additionally needs pdo_pgsql.

```sh
git clone --branch build/00.05.00 https://github.com/paulkakell/twitchybutt.git
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

The administrator command prompts for a password and confirmation, has no default credentials and refuses to promote an existing member. Passwords require 12 characters with letters and numbers and at most 72 UTF-8 bytes. Null bytes are rejected. Keep environment files, keys, database, sessions and logs private. Only public/ may be a web document root; the development server is not a public deployment solution.

**Upgrade compatibility:** apply the additive migration before starting the new code. Existing sessions without a generation stamp must sign in again. Do not overwrite a working APP_KEY. Stop and restart workers during upgrades; review rollback guidance before reverting a site with completed password resets.

## Implemented features and examples

**Accounts:** register at /register, sign in at /login, and use POST logout. Members cannot enter /studio. Public signup cannot choose roles, verification timestamps or session versions.

**Email verification:** open /account/security and request a signed link. It expires after 60 minutes, requires the matching signed-in account and is invalidated by an email change or password-reset generation. Verification does not establish age, identity or performer consent and does not grant administrator privileges.

**Password recovery:** use /forgot-password. Known and unknown addresses receive the same public response and enqueue the same kind of encrypted job. A delivered reset link lasts 30 minutes and is single-use; enter your email and new password. Success rotates the password/session generation, revokes existing authenticated sessions on their next request and requires a fresh sign-in. In-flight responses cannot be recalled. Recovery does not automatically verify the email or change the role.

**Mail setup:** account email is off by default. Configure the creator-owned SMTP service, canonical APP_URL and database worker before setting CMS_ACCOUNT_MAIL_ENABLED=true. Outside local-loopback development, account links require HTTPS and SMTP requires implicit TLS. Log delivery is prohibited. See [every new setting and local/live examples](docs/iterations/00.05.00.md). Encrypted jobs use the creator's APP_KEY; raw failed-job storage is disabled.

```sh
php artisan queue:work database --queue=account-mail --sleep=1 --tries=3 --timeout=30
# Schedule this cleanup hourly in the creator's environment:
php artisan auth:clear-resets
```

**Publishing:** /studio/posts/new accepts escaped plain text. Select general/published and price 0 for public access. Restricted and unclassified posts remain private drafts; those labels are not legal exemptions. No media upload is included.

**Paid access:** a general post priced at 20 is locked without an unexpired, unrevoked entitlement. No HTTP endpoint grants entitlements here, and test fixtures are not settlement evidence. Existing paid access does not call a central licensing service.

**Invoice previews:** signed-in buyers request immutable TEST snapshots. A 20 TEST quote records 20.000000 total, 0.400000 licensing and 19.600000 creator share. Browser totals and settlement fields are ignored. Reusing a buyer/idempotency key returns its original quote; using it for a different purchase returns 409. Only the buyer can read the quote. No tax/refund policy is implied by this arithmetic.

**Reporting:** /report accepts throttled text reports without an account; administrators read /studio/reports. Attachments, notifications and a complete statutory case workflow are not implemented.

**Diagnostics and privacy:** /up is liveness, not dependency readiness. cms:doctor checks configuration and database without printing secrets. Approved JSON events retain only safe typed identifiers. Arbitrary messages/context and exception class names are redacted. New mail events report queue/process/failure status without addresses or links. Infrastructure access logs and SMTP-provider handling need separate review.

## Configuration, tests and release rule

.env.example, docs/OPERATIONS.md and docs/iterations/00.05.00.md describe settings. Payment and restricted-publication flags remain false-only. Production rejects debug output, insecure cookies and ephemeral sessions/rate limits; these checks do not authorize deployment.

```sh
composer validate --strict
composer audit --locked
vendor/bin/pint --test
vendor/bin/phpstan analyse --memory-limit=1G
vendor/bin/phpunit
python3 scripts/security_scan.py
python3 -B docs/roadmap/validate.py
python3 -B -m unittest discover -s tests/security -v
python3 -B -m unittest discover -s tests/roadmap -v
```

Run tests only against disposable databases. CI repeats fresh SQLite/PostgreSQL installs, migrations forward/backward, syntax/style/types, full application/regression tests, cached builds, real HTTP/CSRF checks and small performance budgets. The account smoke check adds loopback SMTP delivery and concurrent reset redemption without external recipients. Actual results, including failed runs, are recorded by exact commit in the PR and working report. A planned test is not a pass.

CI retains allowlisted test and sanitized scanner evidence for 30 days without uploading application logs, session files or mail bodies. It uses repository-read-only permissions and pinned actions. The scoped Semgrep rules are not a comprehensive audit; dependency advisories are time-specific. Evidence inventory is not signed provenance or a complete SBOM.

**All unresolved findings above Low block release.** Unknown severity, missing/failed/stale scans, wrong-candidate evidence and incomplete required reviews also block. Accepted/deferred is not fixed. The full gate defaults to release mode; --development never authorizes release. Branch/tag protections and signed external approvals are still unfinished, so this is a CLI/CI check, not an administrator-proof permission boundary.

## Remaining release requirements

MFA, device-specific session controls, privacy lifecycle, readiness/alerts, browser accessibility, complete deployment hardening and independent security review remain unfinished. So do secret-history scanning, actual deployment-image assessment, production mail-provider verification, signed provenance and durable release evidence.

No testnet/mainnet contract, real settlement, production token/network/treasury configuration, subscriptions, automated renewals, refunds/taxes, media pipeline, performer/viewer verification, turnkey deployment/upgrades/backups or final commercial license is approved. Do not accept customer funds or publish restricted content with this preview.

See CHANGELOG.md, docs/ARCHITECTURE.md, docs/SECURITY.md and docs/iterations/00.05.00.md. Current tracking issue: #7. Earlier branches/PRs remain intact; no release tag or main merge is implied.

Copyright remains with the project owner. Public visibility does not grant an open-source or commercial-use license. Third-party packages retain their licenses; inspect them with composer licenses.
