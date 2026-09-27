# Architecture: 00.03.00

Status: development foundation. Tracking issue #1. Single creator business per installation; not a multi-tenant central platform.

```text
Browser -> creator-owned Laravel app -> creator-owned SQL database
                     |                       |
                     +-> private sessions    +-> users / posts / quotes / entitlements / reports
                     +-> redacted JSON logs

Future: creator app -> independently verified payment integration -> split contract
        creator wallet receives creator allocation; licensor wallet receives agreed 2%
        No blockchain integration or central licensing service runs in this release.
```

Laravel 13 and Blade provide authentication primitives, CSRF, routing, validation, templates and migrations without a JavaScript build dependency. SQLite is the local preview default; PostgreSQL is an independently tested alternative. All application state remains in the creator installation. No content, previews, complaints or personal records are sent to the licensor.

## Trust boundaries

The browser cannot choose roles, invoice totals, fee rates, settlement status or entitlements. Administrator-only routes require server-side authorization. Paid post bodies are rendered only after access policy checks. The catalogue selects summary fields and excludes restricted/unclassified/draft posts. Classification always takes precedence over entitlement and price for members.

The `InvoiceService` locks the buyer and post during creation, snapshots integer totals, and uses a unique buyer/key constraint. Invoice model mutations are forbidden; this is application-level protection, not a tamper-resistant ledger against a database owner or direct query-builder writes. Invoice status is permanently quote-only here. There is no callback that can mark settlement or grant access. Existing entitlement fixtures work without contacting a licensor service.

## Amount contract

Six decimal places are a local TEST convention, not a selected production token. Parse decimal strings, reject exponent/negative/overprecision/ambiguous forms, cap prices at 1,000,000 TEST. Zero means a free post; minimum paid price is 0.000050 TEST. For positive integer gross G: fee = floor(G * 200 / 10000); creator = G - fee. Display all six places. Amounts and multiplication stay within 64-bit limits. Final tax base, gas payer, refunds and production token precision remain design decisions.

## Routes (HTML, session and CSRF protected writes)

GET `/`: paginated public catalogue. GET `/posts/{id}`: authorized body or paywall; inaccessible classified/draft posts return 404. GET/POST `/register` and `/login`: member account forms. POST `/logout`: session termination. GET `/account`: buyer-only quote history. POST `/posts/{id}/invoices`: quote with UUID `idempotency_key`; duplicate purchase reuses snapshot and cross-post reuse returns 409. GET `/invoices/{uuid}`: owner-only quote; other users receive 404. POST `/checkout`: always 503. No success or blockchain callback endpoint exists.

GET `/studio`, GET `/studio/posts/new`, GET `/studio/posts/{id}/edit`, POST `/studio/posts`, PUT `/studio/posts/{id}`: administrator publishing. GET/POST `/report`: public text reporting. GET `/studio/reports`: administrator-only inbox. GET `/up`: process liveness, not database/payment readiness.

## Compatibility

This is the first schema and route set. The project version uses two digits per component, not an unqualified claim of strict SemVer syntax. Next additive scope is 00.04.00; a released 00.03.00 correction would be 00.03.01. Work in this not-yet-released milestone remains traceable to exact commits on build/00.03.00.

## Sources

Official framework release/runtime requirements: https://laravel.com/docs/13.x/releases
Authentication/session guidance: https://laravel.com/docs/13.x/authentication
GitHub token-trigger behavior: https://docs.github.com/en/actions/how-tos/write-workflows/choose-when-workflows-run/trigger-a-workflow
Reviewed 2026-09-26 America/Denver. These are implementation references, not legal clearance.
