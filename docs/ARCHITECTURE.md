# Architecture: 00.04.00

Status: unreleased development preview, security iteration 1. Tracking issue #5. Single creator business per installation; not a multi-tenant central platform.

```text
Browser -> global security headers -> creator-owned Laravel app -> creator-owned SQL database
                    |                          |                       |
                    |                          +-> private sessions    +-> users / posts / quotes / entitlements / reports
                    |                          +-> allowlisted JSON logs
                    +-> exception finalizer applies headers on errors

Repository commit -> locked tests/build + advisory/custom SAST -> sanitized evidence
                                                              -> release-readiness gate
                                                                 DENY until required reviews complete

Future: creator app -> independently verified payment integration -> split contract
        creator wallet receives creator allocation; licensor wallet receives agreed 2%
        No blockchain integration or central licensing service runs in this preview.
```

Laravel 13 and Blade provide authentication primitives, CSRF, routing, validation, templates and migrations without a JavaScript build dependency. SQLite is the local preview default; PostgreSQL is an independently tested alternative. All application state remains in the creator installation. No content, previews, complaints or personal records are sent to the licensor.

## Trust boundaries

The browser cannot choose roles, invoice totals, fee rates, settlement status or entitlements. Administrator-only routes require server-side authorization and an explicit true administrator value. Paid post bodies are rendered only after access policy checks. The catalogue selects summary fields and excludes restricted/unclassified/draft posts. Classification always takes precedence over entitlement and price for members.

The InvoiceService locks the buyer and post during creation, snapshots integer totals, and uses a unique buyer/key constraint. Invoice model mutations are forbidden; this is application-level protection, not a tamper-resistant ledger against a database owner or direct query-builder writes. Invoice status is permanently quote-only here. There is no callback that can mark settlement or grant access. Existing entitlement fixtures work without contacting a licensor service.

The daily Monolog processor replaces unknown messages, discards arbitrary context and retains only typed identifiers. Exception class names are replaced with Throwable. Server-generated request IDs are not copied from untrusted client headers. External logging channels and web-server responses remain deployment responsibilities. See security/THREAT_MODEL.md for adversarial scenarios and residual work.

## Amount contract

Six decimal places are a local TEST convention, not a selected production token. Parse decimal strings, reject exponent/negative/overprecision/ambiguous forms, cap prices at 1,000,000 TEST. Zero means a free post; minimum paid price is 0.000050 TEST. For positive integer gross G: fee = floor(G * 200 / 10000); creator = G - fee. Display all six places. Amounts and multiplication stay within 64-bit limits. Final tax base, gas payer, refunds and production token precision remain design decisions.

## Routes (HTML, session and CSRF protected writes)

GET `/`: paginated public catalogue. GET `/posts/{id}`: authorized body or paywall; inaccessible classified/draft posts return 404. GET/POST `/register` and `/login`: member account forms. POST `/logout`: session termination. GET `/account`: buyer-only quote history. POST `/posts/{id}/invoices`: quote with UUID idempotency_key; duplicate purchase reuses snapshot and cross-post reuse returns 409. GET `/invoices/{uuid}`: owner-only quote; other users receive 404. POST `/checkout`: always 503. No success or blockchain callback endpoint exists.

GET `/studio`, GET `/studio/posts/new`, GET `/studio/posts/{id}/edit`, POST `/studio/posts`, PUT `/studio/posts/{id}`: administrator publishing. GET/POST `/report`: public text reporting. GET `/studio/reports`: administrator-only inbox. GET `/up`: process liveness, not database/payment readiness. Security-regression routes are registered in tests only and do not exist in the application route file.

## Release evidence and compatibility

Version 00.04.00 is additive security/testing work on the 00.03.00 application and 00.03.01 roadmap snapshot. The project uses two digits per version component. Exact commits remain the execution identity; no release tag is created while release-readiness is unmet.

The five-table schema, public route set, fee arithmetic and package versions are unchanged. JSON log consumers must adapt to replacement of arbitrary message/context and exception class names. Reverting this iteration requires code rollback, not a destructive database migration. Preserve creator-owned state and keys.

The CLI gate and CI enforce failed status for above-Low/unknown findings and incomplete evidence. They do not configure branch protection or prohibit administrators from bypassing CI. Independent assessments, signed external approvals and deployment enforcement remain pending. No production release follows merely from passing unit tests.

## Sources

Official framework release/runtime requirements: https://laravel.com/docs/13.x/releases
Authentication/session guidance: https://laravel.com/docs/13.x/authentication
Error response customization: https://laravel.com/docs/13.x/errors
Logging: https://laravel.com/docs/13.x/logging
Semgrep CLI: https://semgrep.dev/docs/cli-reference

Implementation references, not legal clearance. Current release evidence is recorded in the iteration PR and designated Drive folder.
