# 00.05.00 validation history

Tracking issue #7. This is a construction record, not release approval. Each run tests its own exact commit; later results do not retroactively change earlier failures.

1. d3641168504645c9fb8390b384c496b9978b58b9 / run 36296874702: new account implementation; validation stopped at an unused import in AccountRecoveryTest. Security job passed; skipped application tests were not counted as passes.
2. b6154bd56efc2de31266030a020318ffa96211b7 / run 36297070083: syntax, style and types passed; 100 application tests executed, exposing two fixture errors. Entitlement is intentionally guarded and requires explicit fixture forceFill. The test framework constructed the host from the URL rather than an overridden HTTP_HOST variable. Fix fixtures instead of loosening model protection or dropping the host check.
3. 3ae141e8cb8581a679f1181ca2e3c02cd929c432 / run 36297349130: both databases passed 100 tests and 20,388 assertions. PostgreSQL passed all validation, including local SMTP delivery, real HTTP/CSRF, two-session revocation and concurrent reset redemption. SQLite passed the suite and all account HTTP/SMTP flows, but one concurrent reset worker exited with an error. No duplicate successful redemption was reported. This failure blocked the run before SQLite's no-dev install; release-readiness was skipped, not approved.

The next correction adds bounded framework transaction retries for reset redemption (at most five attempts). A retry rechecks the token from a fresh transaction after rollback, retaining password/generation/token atomicity. It does not treat an unexpected exception as success or ignore a failing worker. PasswordReset events and completion logs are deferred until commit to avoid reporting rolled-back attempts as successful. The existing two-process test continues requiring one success, one rejection and two clean process exits.

SQLite read-to-write lock contention is the working diagnosis for the concurrency-only failure; the first smoke script intentionally did not print raw worker exceptions because SQL diagnostics can contain private fields. The full rerun must establish that the correction works on both supported databases. Framework retry behavior reference: https://laravel.com/docs/13.x/database#handling-deadlocks .

README, change log, architecture and security documentation now describe 00.05.00, configuration examples, session compatibility, migration/rollback and remaining review requirements. Exact final results, artifacts and candidate identity will be recorded in the draft PR and working Drive report after execution.

All unresolved findings above Low and all missing/unknown/stale release evidence remain blocking. No main merge, tag, production mail activation, deployment, media enablement or funds transfer occurs through validation. The broader independent, secret-history, runtime-image and full M01 reviews remain pending.
