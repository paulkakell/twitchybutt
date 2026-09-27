# Release candidate notes: 00.03.00

Status: implementation under review, not a tagged release. Additive application foundation based on requirements 00.02.00. Reference #1.

## Included

Member/session accounts and local administrator provisioning; responsive server-rendered studio; classified plain-text posts; free/paid authorization; six-decimal integer TEST quotes with a fixed 2% fee and immutable snapshots; owner-only idempotent invoice history; public report intake; schema and development rollback; structured private logging; executable checks and operator documentation.

## Excluded

Real or testnet transfers, deployed split contract, payment verification or automated entitlement grants, recurring debits, taxes/refunds, media pipelines, external age/performer verification, statutory complaint automation, email/reset/MFA, audited licensing terms, production container deployment, automatic backups and release tagging.

## Validation record

Actual checks must be read from the GitHub Actions runs and the build report stored in the designated Drive folder. Planned checks are not passes. The full suite includes SQLite/PostgreSQL integration, regression, amount/performance tests, syntax/lint/type analysis, dependency audit, targeted source checks, forward/rollback migrations, configuration/route/template compilation and live HTTP CSRF/session testing. The local assistant container cannot download dependencies or run database-backed PHP; independent GitHub runners execute those checks.

## Compatibility and rollback

No prior deployed database or API is changed. Migration rollback destroys this initial schema and is tested only on disposable databases. Use matching code/database backups for any development data worth retaining. The existing main baseline and supplied prior requirements packages remain available. Preserve main pending review. Tag the approved exact commit as 00.03.00 only after gates are reviewed.

## Copyable commit notes

```text
feat(00.03.00): add the executable creator CMS foundation (#1)

Add member authentication and local administrator provisioning.
Add classified posts, private paid-body authorization and a creator studio.
Add exact 2% TEST invoice snapshots with owner access and idempotency.
Keep checkout and restricted publication disabled; never fabricate settlement.
Add report intake, migrations, structured logs, CI and operator documentation.
Add unit, integration, regression, HTTP and performance smoke checks.
No real funds, contract deployment, main merge or release tag.
```
