# Change log

## 00.04.00 - Security and testing iteration 1 (unreleased)

Classification: additive security/testing behavior with authorization and test-harness fixes. Reference #5. Based on roadmap commit 3398211a9d4c782d811b257b6372b10c87812813. This implements part of M01, not the complete account/security milestone.

Add a fail-closed above-Low release policy with exact-commit scan evidence, age/coverage checks, required reviews and negative regression tests. Accepted/deferred issues are not remediation; unknown severity and incomplete reviews block. Add real Composer and Semgrep scans, digest-pinned SAST execution and sanitized retained artifacts. Broader maintained SAST, secret-history and deployment-image scanning remain incomplete.

Add a role-boundary threat model, runtime log-redaction tests, deny-by-default message/context redaction, global/error-response security headers and server-generated request IDs. Fix the manage-content gate to deny a null administrator attribute instead of returning null from a bool callback. Fix new logging tests to use LogManager::forgetChannel rather than an unsupported purge method; assertions were not disabled.

Compatibility note: unknown log messages now become cms.log.redacted, unsafe context is discarded, and exception types become Throwable. Consumers of older raw messages must adapt. Routes, schema, fee arithmetic, package versions and environment options are unchanged. No migration is added.

Initial commit 346ccf67d2c95395c13981825fddd2c448c119a1 and diagnostic correction 8db9f661b8ab7a5074127cd1f7e2b7a598e704c8 retain failed validation evidence. Runs 36293888537 and 36294077459 found the logging test API mismatch and nullable-role error. Final execution results belong to the exact commit and Actions run in the PR/Drive report; no earlier failed or skipped check is reported as passing.

MFA, account recovery, verified mail and other M01 requirements remain open. No release tag, main merge, deployment, media publishing or payment transfer. See docs/iterations/00.04.00.md for all release controls, commands and rollback guidance.

## 00.03.01 - Product roadmap documentation (review pending)

Classification: additive documentation and contributor tooling; fixes the missing maintainable product backlog. Reference #3, based on PR #2 at b14dc4cf32e693808dcd326496db5336b7721399. Documentation revision is tracked in docs/roadmap/VERSION; root application VERSION remains 00.03.00.

Add ROADMAP.md, 100 stable-ID items across ten proposed milestones, 12 open decisions, acceptance criteria, dependencies, an editable native Google Sheets planning copy, an ideas inbox and all 16 release controls. Provide a compact repository snapshot and feature-request/copyable item templates. Distinguish implemented preview work from proposed or released scope. Dates, staffing and effort remain uncommitted; no automatic Sheet/GitHub synchronization is implied.

Add a dependency/ID validator with 13 regression tests and rerun full existing read-only CI. No runtime behavior, dependency lock, schema, secret or payment configuration changes. Exact commit and validation evidence are attached to the roadmap PR and Drive revision report. No application tag, main merge or deployment is included.

## 00.03.00 - Application foundation (unreleased)

Classification: additive foundation with construction fixes. Reference #1. Requirements baseline 00.02.00. No existing deployed API or database is changed.

Add Laravel 13 structure, member/session authentication, local administrator provisioning, classified text posts and creator studio, private paid-body authorization, exact 200-basis-point integer quote calculation, immutable invoice snapshots, idempotency and anonymous report intake. These changes make the initial requirements executable without pretending that settlement exists.

Add disabled-feature guards, escaped templates, CSRF/rate limits, security headers, structured private logs, SQLite/PostgreSQL schema, reversible development migrations, dependency locks, tests, CI and operator/rollback documentation. Fee rounding floors the license share; the creator receives the remainder. No tax/refund behavior is implied.

Fix missing Mockery discovered in CI; reject malformed email arrays, null-byte passwords and inputs above bcrypt's byte limit. Enforce 64-bit amounts and production persistent-state settings. Correct test-environment cache/session isolation without weakening rate limits. Preserve failed-run evidence. Pin Node24 checkout and the PostgreSQL image digest, then remove dependency-bootstrap repository-write jobs after the lock is committed.

Key commits: initial application 88cbd860cdba41916c91b6fa45a057f162668687; initial lock 79a8ad97af85cd77237b16d5d8e7d0bf9748e64c; credential/test correction a469015379db0053bcb79e2f46a1d7096b1449f7; corrected lock 989170c543fac00c3dc27df660184699cc84c669. Final commit and executed validation are recorded in the PR and Drive report.

No real funds, blockchain contract, treasury configuration, production deployment, main merge or release tag is included.

## 00.02.00 - Requirements baseline

Content-neutral creator software permitting lawful adult content; Utah licensor jurisdiction; creator-owned infrastructure; 2% fee through supported crypto checkout. Requirements only; prior versioned documentation packages remain available.
