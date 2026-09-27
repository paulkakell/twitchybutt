# TwitchyButt CMS

Version **00.04.00**, account/security iteration 1. Self-hosted, content-neutral creator software. **Unreleased development preview, not a production platform.** M01 is incomplete and release approval remains blocked.

Creators operate their own application and database. No central content hosting is implemented. The intended payment flow allocates a 2% licensing fee; this preview only calculates **TEST invoice quotes**. No wallet is connected, no funds are accepted, and quotes never unlock paid content.

## Editable roadmap

The [Google Sheets roadmap](https://docs.google.com/spreadsheets/d/1bdbGDfaQMY68vkWfJhkeO3uBuDx-qHyqr8Vjjp1av_A/edit) is the editable planning copy. Use Ideas for additions and permanent TB identifiers for accepted items. See [ROADMAP.md](ROADMAP.md) for the 00.03.01 baseline snapshot and [00.04.00 iteration notes](docs/iterations/00.04.00.md) for current work, release blockers and next steps. There is no automatic Sheet/GitHub synchronization; preserve live user additions before updating snapshots.

## Run a local preview

Use an isolated development machine with 64-bit PHP 8.3+, Composer 2, PDO SQLite, mbstring, XML/DOM, ctype, fileinfo and OpenSSL. PostgreSQL also requires pdo_pgsql. Composer checks the locked package requirements.

```sh
git clone --branch build/00.04.00 https://github.com/paulkakell/twitchybutt.git
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

Administrator provisioning prompts for a password and confirmation. No default administrator or password exists. Existing members are not silently promoted. Passwords require at least 12 characters with letters and numbers and at most 72 UTF-8 bytes; null bytes are rejected. Keep `.env`, the database, sessions and logs private. The development server is not a public deployment solution. Only `public/` may be a web document root.

## Implemented features and examples

**Accounts:** register at `/register`, sign in at `/login`, and sign out with the form. Members cannot access `/studio`. An absent/null administrator flag denies access. Email verification, password recovery and MFA are not implemented.

**Publishing:** visit `/studio/posts/new`, enter plain text, choose `general` and `published`, and use price `0` for open access. Drafts stay private. Edit and unpublish posts through the studio. HTML is escaped; media uploads are not included.

**Classification:** `general`, `restricted`, or `unclassified`. Restricted and unclassified posts can be saved as private drafts but cannot be published, including through forged requests. These labels are not legal exemptions.

**Paid access:** price `20` creates a locked general post. Reading requires a valid, unexpired, unrevoked entitlement. No HTTP endpoint grants entitlements in this preview; test fixtures are not payment evidence. Existing valid access does not depend on a licensing service.

**Invoice previews:** signed-in members can request a quote for a published general paid post. For 20 TEST, the server records 20.000000 total, 0.400000 fee, and 19.600000 creator allocation. Browser-supplied prices, fees and payment status are ignored. Duplicate buyer/idempotency keys return the original snapshot; reuse for another post returns 409. Quotes are visible only to their buyer and do not initiate payments.

**Reports:** `/report` accepts text reports without an account; administrators read them at `/studio/reports`. No attachments, emergency response, notifications or complete statutory case workflow are implemented.

**Diagnostics and logs:** `/up` is liveness only. `php artisan cms:doctor` checks configuration and database connectivity without printing secrets. The daily JSON channel now allows only approved event names and typed identifiers. An arbitrary message becomes `cms.log.redacted`; private/nested context and traces are discarded. Exception events retain `Throwable`, not a class name that might contain a filesystem path. Deployment and web-server access logs still need separate review.

**Response protection:** security headers, private/no-store cache policy and server-generated request IDs cover normal application responses, liveness and exception responses. Client-supplied request IDs are not trusted. The external static-file server needs its own configuration.

## Configuration and validation

See `.env.example` and `docs/OPERATIONS.md` for exposed settings and examples. Both `CMS_PAYMENTS_ENABLED` and `CMS_RESTRICTED_PUBLISHING_ENABLED` are false-only reserved flags: setting either true prevents startup. Production mode rejects debugging, insecure cookies and ephemeral session/cache stores; this does not certify production readiness. This iteration adds no environment variables or dependency changes.

```sh
composer validate --strict
composer audit --locked
vendor/bin/pint --test
vendor/bin/phpstan analyse --memory-limit=1G
vendor/bin/phpunit
python3 scripts/security_scan.py
python3 -B -m unittest discover -s tests/security -v
python3 -B -m unittest discover -s tests/roadmap -v
```

Run tests only against disposable databases. CI checks fresh SQLite/PostgreSQL installations, dependency advisories, syntax/style/types, source guardrails, forward/backward migrations, unit/integration/regression behavior, cached builds, real HTTP/CSRF behavior and performance smoke budgets. Test configuration isolates cache/session state from runner variables. Validation artifacts retain JUnit, audit, dependency inventory/licenses and exact commit information for 30 days; application logs and `.env` are excluded.

## Mandatory release gate

All unresolved findings above **Low** block release. Unknown severity, failed or empty scans, wrong-commit/stale evidence and missing required reviews also block. Marking a finding accepted or deferred does not fix it. Independent review, complete M01 acceptance, secret-history and deployment-image evidence are currently pending.

```sh
python3 scripts/security_gate.py \
  --evidence build/security-evidence.json \
  --commit "$(git rev-parse HEAD)" --version "$(cat VERSION)"
```

The default is release mode and returns a failing exit code while any gate is unmet. The `--development` option checks available scan findings only and explicitly never authorizes release. Do not use that option or `continue-on-error` in a release job. The separate `release-readiness` job remains failing until current approval evidence exists; green application and scan jobs alone are not approval.

Current SAST uses a digest-pinned Semgrep engine and six project-specific rules, not a comprehensive maintained security ruleset. The dependency inventory is not a signed SBOM. Protected branch/tag rules, signed external approvals and a deployment pipeline are not configured, so repository administrators can still bypass workflows. See `security/THREAT_MODEL.md`, `security/release-policy.json` and `docs/iterations/00.04.00.md` for scope, limitations and reviewer-controlled approval guidance.

## Remaining release gates

No real or testnet transfers, split contract, production token/network, treasury address, subscriptions/automatic renewals, refunds/taxes, media pipeline, performer/viewer verification, statutory case automation, email/reset/MFA, turnkey deployment/upgrades/backups or final commercial license is implemented or approved. Do not accept customer funds or publish restricted content with this preview.

See `CHANGELOG.md`, `docs/ARCHITECTURE.md`, `docs/OPERATIONS.md`, `docs/SECURITY.md`, `security/THREAT_MODEL.md`, and `docs/iterations/00.04.00.md`. Current tracking issue: #5. Prior application and roadmap PRs remain separate. No main merge, release tag or deployment is included.

Copyright remains with the project owner. Public visibility does not grant an open-source or commercial-use license. Third-party packages retain their own licenses; use `composer licenses` to inventory them.
