# Change log

## 00.03.00 - Application foundation (unreleased)

Classification: additive foundation with construction fixes. Reference #1. Requirements baseline 00.02.00. No existing deployed API or database is changed.

Add Laravel 13 structure, member/session authentication, local administrator provisioning, classified text posts and creator studio, private paid-body authorization, exact 200-basis-point integer quote calculation, immutable invoice snapshots, idempotency and anonymous report intake. These changes make the initial requirements executable without pretending that settlement exists.

Add disabled-feature guards, escaped templates, CSRF/rate limits, security headers, structured private logs, SQLite/PostgreSQL schema, reversible development migrations, dependency locks, tests, CI and operator/rollback documentation. Fee rounding floors the license share; the creator receives the remainder. No tax/refund behavior is implied.

Fix missing Mockery discovered in CI; reject malformed email arrays, null-byte passwords and inputs above bcrypt's byte limit. Enforce 64-bit amounts and production persistent-state settings. Correct test-environment cache/session isolation without weakening rate limits. Preserve failed-run evidence. Pin Node24 checkout and the PostgreSQL image digest, then remove dependency-bootstrap repository-write jobs after the lock is committed.

Key commits: initial application 88cbd860cdba41916c91b6fa45a057f162668687; initial lock 79a8ad97af85cd77237b16d5d8e7d0bf9748e64c; credential/test correction a469015379db0053bcb79e2f46a1d7096b1449f7; corrected lock 989170c543fac00c3dc27df660184699cc84c669. Final commit and executed validation are recorded in the PR and Drive report.

No real funds, blockchain contract, treasury configuration, production deployment, main merge or release tag is included.

## 00.02.00 - Requirements baseline

Content-neutral creator software permitting lawful adult content; Utah licensor jurisdiction; creator-owned infrastructure; 2% fee through supported crypto checkout. Requirements only; prior versioned documentation packages remain available.
