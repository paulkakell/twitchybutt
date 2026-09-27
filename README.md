# TwitchyButt CMS

Application version **00.07.00**, on the `build/01.00.00` delivery track. **Unreleased development preview; release approval is blocked.** This increment adds creator-local private media. It does not enable real payments or public restricted-content operations.

Content-neutral creator software: each creator owns their application, domain, database, media, mail service and customer records. The intended supported checkout allocates 2% to licensing; current TEST quotes do not transfer money or grant access. No central content hosting, media proxy or licensor backup store is implemented.

## Run an isolated local preview

Use 64-bit PHP 8.3+, Composer 2 and the locked extensions. SQLite requires pdo_sqlite; PostgreSQL additionally requires pdo_pgsql. Media opt-in requires GD with JPEG/PNG/WebP support and a PCNTL-enabled worker; optional video requires compatible FFmpeg/FFprobe. CI records the actual external tool versions rather than assuming they are Composer dependencies.

```sh
git clone --branch build/01.00.00 https://github.com/paulkakell/twitchybutt.git
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

Do not overwrite an existing APP_KEY. The administrator command prompts privately for a password and refuses to promote an existing member. The creator must enroll MFA before using the studio. Only public/ may be the web document root; the development server is not production hosting. Keep database, configuration, keys, sessions and logs private.

## Implemented examples

**Accounts:** members register/sign in, verify an email, recover a password and manage sessions. Account email defaults off until creator SMTP and the encrypted database mail queue are configured. Signed verification links last 60 minutes; hashed single-use reset tokens last 30 minutes. Recovery requires fresh login, revokes existing sessions on their next request and preserves MFA. Verification is not proof of adulthood, identity or performer consent.

**MFA:** open /account/mfa, confirm the current password, enroll an authenticator and save the ten recovery codes offline. Administrators require MFA; members can opt in. TOTP and recovery proofs are single-use across competing requests. Replacement requires current password plus recently completed MFA and keeps the old factor until confirmation. There is no public email-only MFA-disable shortcut. Lost-all-factor operations still need review.

**Sessions:** /account/sessions lists owner-scoped management references and timestamps. Confirm a password to revoke one or all sessions. Default idle/absolute limits are 30/720 minutes, bounded in configuration. Existing pre-registry sessions must sign in again. A response already being delivered cannot be recalled.

**Publishing:** create a post in /studio, choose general/published and price 0 for a public post. Text is escaped, not executed as HTML. Restricted/unclassified content remains private drafts. A general post priced 20 is locked without current entitlement. No route grants entitlements by pretending a payment succeeded.

**Private media:** save a post, then choose Manage private media. With media enabled, upload JPEG/PNG/WebP or separately enabled MP4, add alternative text and set display order. Sources are quarantined privately; workers generate bounded derivatives. Only ready media appears to authorized readers. Image maximum 8 MiB/20 million pixels; MP4 maximum 64 MiB/10 minutes. Relative signed links last 5 minutes and always recheck current access. Revocation/deletion blocks subsequent requests; the studio removes local files and releases quota only after cleanup. Cloud storage, resumable upload, adaptive streaming, isolated native decoders and caption support remain open.

**Invoice previews:** 20 TEST produces 20.000000 gross, 0.400000 licensing and 19.600000 creator share. Amounts are bounded integer snapshots with buyer-scoped idempotency. Browser totals and settlement fields are ignored; only the buyer can read a quote. Network fees, taxes and refunds are not implemented by this arithmetic.

**Reporting:** /report accepts throttled plain-text reports without an account. MFA-authorized administrators inspect /studio/reports. Complete case/removal deadlines, attachments and provider workflows remain open.

**Operations:** cms:doctor checks configuration/database without secrets. cms:media-status reports local asset counts, reservations and stalled work. Known-event logs redact arbitrary content and credentials; startup failures do not expose debug details. Infrastructure logging and alerting remain separate responsibilities.

## Configuration, workers and testing

[Operator settings](docs/OPERATIONS.md) and [00.07.00 examples/rollback](docs/iterations/00.07.00.md) cover every new option. Account/MFA details remain in their versioned documents. Media, video, real payments and restricted publication default off. Payment/restricted flags are false-only in this preview.

```sh
# Creator account-mail worker, after explicit SMTP opt-in:
php artisan queue:work database --queue=account-mail --sleep=1 --tries=3 --timeout=30
# Separate media worker, after explicit media opt-in:
php -d memory_limit=512M artisan queue:work media --queue=media --sleep=1 --tries=1 --timeout=240 --memory=512
# Daily security pruning; hourly expired-reset cleanup:
php artisan cms:security-prune
php artisan auth:clear-resets
# Disposable databases only:
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

CI repeats full tests on SQLite/PostgreSQL, fresh locked and no-dev installs, migrations, cached builds, scoped scans and actual loopback HTTP/SMTP/media/concurrency checks. Retained artifacts identify the exact tested commit and omit application logs, mail bodies and credentials. A queued, skipped or planned test is not a pass. Native worker isolation, production tool-image assessment, real-provider delivery and browser/accessibility testing require additional evidence.

## Roadmap and release rule

[Editable roadmap](https://docs.google.com/spreadsheets/d/1bdbGDfaQMY68vkWfJhkeO3uBuDx-qHyqr8Vjjp1av_A/edit), [dated baseline](ROADMAP.md), [1.0 acceptance](docs/ONE_ZERO_ACCEPTANCE.md), [architecture](docs/ARCHITECTURE.md), [security review](docs/SECURITY.md), [change log](CHANGELOG.md).

**Every unresolved security finding above Low blocks release.** Unknown severity, missing/failed/stale/wrong-candidate evidence and incomplete mandatory reviews also block; accepted/deferred is not fixed. The release gate defaults to denial and development mode never approves release. Independent security, secret-history, deployment-image and complete milestone evidence remain missing. Repository protections and externally controlled signed approvals are unfinished; CLI/CI is not an administrator-proof permission barrier.

Apply migrations before new code, preserve current credentials/APP_KEY and keep matching private files/database for rollback. Media-schema rollback does not remove files and must not reset quota over retained storage. Prefer a forward fix, not reopening pre-MFA code. No main merge, tag, deployment or real funds transfer is authorized here.

Copyright remains with the project owner. Public repository visibility is not an open-source or commercial-use license. Third-party packages retain their own licenses; inspect them with composer licenses.
