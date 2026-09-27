# Operator notes: 00.03.00

Use this release only for isolated development. Start with README commands. The software is not a turnkey production install yet. Do not put customer funds, sensitive verification records, or restricted public content into this preview.

## Configuration options and examples

`APP_NAME`: display brand, escaped in templates; for example `Jane's Studio`. `APP_ENV`: `local` for loopback preview, `testing` only for tests; production behavior has additional guards but is not a production endorsement. `APP_KEY`: generate with `php artisan key:generate`, keep private and back it up; never use the fixture key in phpunit.xml or the CI workflow. `APP_DEBUG`: false. `APP_URL`: the canonical origin, locally `http://localhost:8000`.

`DB_CONNECTION`: `sqlite` for a single-machine preview or `pgsql` for PostgreSQL. SQLite defaults to `database/database.sqlite`; a custom `DB_DATABASE` must be an absolute path outside the web root. For PostgreSQL use `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`; configure `DB_SSLMODE=verify-full` and appropriate trusted CA/server settings when applicable. The supplied CI password is disposable test data, never a deployment credential.

`SESSION_DRIVER`: `file` for the local instance; `array` only in tests. `SESSION_SECURE_COOKIE=false` only for loopback HTTP; secure cookies are required by the production guard. Cookie scope is host-only, HTTP-only, SameSite=Lax. Persistent multi-node sessions are not implemented. `CACHE_STORE=file` provides single-node rate limits; `array` is test-only and must not be deployed for limits. Reverse-proxy trust is not configured; do not enable broad forwarded-header trust to fix IP behavior.

`LOG_CHANNEL=daily`, `LOG_LEVEL=info`: creator-local structured JSON, fourteen-day rotation and restrictive file permissions. Exception reporting omits messages and traces to reduce content/secret leakage. Request IDs and exception classes support diagnosis; inspect private development traces separately when needed. No hosted metrics or alert routing exists yet.

`CMS_PAYMENTS_ENABLED=false`, `CMS_RESTRICTED_PUBLISHING_ENABLED=false`: false-only reserved flags. A true value fails startup. There are no wallet keys, treasury settings or supported token configuration to fill in. Six-decimal TEST is a quoting convention only. Never supply seed phrases.

## Validation and troubleshooting

Run `php scripts/prepare.php` before the first dependency install. Run `php artisan cms:doctor` after migrations. Use `php artisan config:clear` after environment edits. `/up` proves the web process responds; it does not prove database readiness. Check the JSON log exception type and request ID when a page returns 500. A 503 at `/checkout` is intentional. Run the entire automated suite before a change; do not edit quoted invoice records to simulate payment.

## Backup and rollback

Development backup: stop writes, securely copy the SQLite database (or a consistent PostgreSQL dump), `.env`/APP_KEY and necessary local storage. Keep backups outside the web root and outside the licensor's infrastructure. Test restores into a separate empty development installation. No automated backup/restore feature is implemented.

The first migration's `down()` drops all five application tables. Forward/rollback tests use disposable databases only. Do not use `migrate:rollback` against data you need. For code rollback, stop the preview, return to the last compatible exact commit and restore its matching database/configuration backup. Baseline main commit db6ddc18af91639bfd8ce808dd97ab2aec5a5227 is documentation-only and cannot run the new schema. A rollback to baseline requires restoring an empty pre-application state, not merely deploying README.md.

## Release gate

No release tag is created while review is outstanding. Preserve main, inspect the pull request and test evidence, then tag the approved release commit as `00.03.00`. Do not attach that tag to a different dependency-lock or code revision. The next infrastructure milestone needs container/TLS packaging, restore automation, verification providers and independently verified testnet payment handling before any public launch.
