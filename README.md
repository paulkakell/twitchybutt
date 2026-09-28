# TwitchyButt CMS

Version **00.03.00**. Self-hosted, content-neutral creator software. **Development preview, not a production platform.**

## Product roadmap

[Read the full product roadmap](ROADMAP.md) or [add ideas in the editable Google Sheet](https://docs.google.com/spreadsheets/d/1bdbGDfaQMY68vkWfJhkeO3uBuDx-qHyqr8Vjjp1av_A/edit). Roadmap documentation revision **00.03.01** contains 100 items, ten proposed milestones, open decisions, acceptance criteria and release controls. Application VERSION is unchanged. Sheet edits do not automatically synchronize to GitHub.

## Community and maintenance

Use the [issue forms](https://github.com/paulkakell/twitchybutt/issues/new/choose) for bugs, documentation corrections and scoped roadmap requests. Ask questions and explore ideas in [Discussions](https://github.com/paulkakell/twitchybutt/discussions). Read [CONTRIBUTING.md](CONTRIBUTING.md) and [SECURITY.md](SECURITY.md) before sharing evidence; never post secrets, private media or vulnerability details publicly.

[Repository administration](docs/REPOSITORY_ADMINISTRATION.md) documents sponsorship prerequisites, Dependabot version/security grouping, review rules and separately controlled GitHub security settings. Configuration does not imply release approval or successful activation of an administrator-only feature.

Creators operate their own application and database. No central content hosting is implemented. The intended payment flow allocates a 2% licensing fee; this release only calculates **TEST invoice quotes**. No wallet is connected, no funds are accepted, and quotes never unlock paid content.

## Run a local preview

Use an isolated development machine with 64-bit PHP 8.3+, Composer 2, PDO SQLite, mbstring, XML/DOM, ctype, fileinfo and OpenSSL. PostgreSQL also requires pdo_pgsql. Composer checks the locked package requirements.

```sh
git clone --branch main https://github.com/paulkakell/twitchybutt.git
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

**Accounts:** register at `/register`, sign in at `/login`, and sign out with the form. Members cannot access `/studio`. Email verification, password recovery and MFA are not implemented.

**Publishing:** visit `/studio/posts/new`, enter plain text, choose `general` and `published`, and use price `0` for open access. Drafts stay private. Edit and unpublish posts through the studio. HTML is escaped; media uploads are not included.

**Classification:** `general`, `restricted`, or `unclassified`. Restricted and unclassified posts can be saved as private drafts but cannot be published, including through forged requests. These labels are not legal exemptions.

**Paid access:** price `20` creates a locked general post. Reading requires a valid, unexpired, unrevoked entitlement. No HTTP endpoint grants entitlements in this release; test fixtures are not payment evidence. Existing valid access does not depend on a licensing service.

**Invoice previews:** signed-in members can request a quote for a published general paid post. For 20 TEST, the server records 20.000000 total, 0.400000 fee, and 19.600000 creator allocation. Browser-supplied prices, fees and payment status are ignored. Duplicate buyer/idempotency keys return the original snapshot; reuse for another post returns 409. Quotes are visible only to their buyer and do not initiate payments.

**Reports:** `/report` accepts text reports without an account; administrators read them at `/studio/reports`. No attachments, emergency response, notifications or complete statutory case workflow are implemented.

**Diagnostics:** `/up` is liveness only. `php artisan cms:doctor` checks configuration and database connectivity without printing secrets. Structured JSON logs retain event names and IDs rather than post bodies or credentials.

## Configuration and validation

See `.env.example` and `docs/OPERATIONS.md` for every exposed setting and examples. Both `CMS_PAYMENTS_ENABLED` and `CMS_RESTRICTED_PUBLISHING_ENABLED` are false-only reserved flags: setting either true prevents startup. Production mode rejects debugging, insecure cookies and ephemeral session/cache stores; this does not certify production readiness.

```sh
composer validate --strict
composer audit --locked
vendor/bin/pint --test
vendor/bin/phpstan analyse --memory-limit=1G
vendor/bin/phpunit
python3 scripts/security_scan.py
```

Run tests only against disposable databases. CI checks fresh SQLite/PostgreSQL installations, dependency advisories, syntax/style/types, source guardrails, migrations forward/backward, unit/integration/regression behavior, cached builds, real HTTP/CSRF behavior and small performance budgets. Test configuration isolates cache/session state from runner variables.

The one-time dependency-lock generation jobs have been removed. Application validation has repository read permission only and installs from composer.lock. The separate community setup job can create labels/discussions on main but cannot write application code or change administrator-only settings. Failed runs and corrections are documented in `docs/VALIDATION_HISTORY.md`; a planned check is not a pass. Actual final results are in the pull request and the Drive build report.

## Remaining release gates

No real or testnet transfers, split contract, production token/network, treasury address, subscriptions/automatic renewals, refunds/taxes, media pipeline, performer/viewer verification, statutory case automation, email/reset/MFA, turnkey deployment/upgrades/backups or final commercial license is implemented or approved on this main baseline. Do not accept customer funds or publish restricted content with this preview.

See `CHANGELOG.md`, `docs/ARCHITECTURE.md`, `docs/OPERATIONS.md`, `docs/SECURITY.md`, and `docs/RELEASE_00.03.00.md`. Foundation and roadmap work have been integrated into main. The newer application candidate remains in draft PR #10, tracked by #9; its unmerged features and release evidence must not be attributed to this main baseline. No release tag has been created.

Copyright remains with the project owner. Public visibility does not grant an open-source or commercial-use license. Third-party packages retain their own licenses; use `composer licenses` to inventory them.
