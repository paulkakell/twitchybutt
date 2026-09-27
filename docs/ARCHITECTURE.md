# Architecture: 00.05.00

Status: unreleased account-security iteration. Tracking issue #7. Single creator business per installation; no central multi-tenant content platform.

```text
Browser -> security headers / configured host check -> creator-owned Laravel app
                                                    |-> session-generation check
                                                    |-> role/content authorization
                                                    |-> creator-owned SQL database
                                                    |   users / posts / quotes / entitlements / reports
                                                    |   reset-token hashes / encrypted mail jobs
                                                    +-> allowlisted JSON event logs

Encrypted job -> creator-owned queue worker -> creator-contracted SMTP -> member inbox
                                                       |
                                       expiring link -> matching creator site

Password reset -> serialized user/token transaction -> increment session generation
Next authenticated request -> reject stale generation -> require fresh login

Repository commit -> locked tests/build + advisory/custom SAST -> sanitized evidence
                                                              -> release-readiness gate
                                                                 DENY until required reviews complete
```

Laravel 13 and Blade provide routing, validation, authentication primitives and templating without a JavaScript build dependency. SQLite is the local-preview default; PostgreSQL is the other tested database target. Mail credentials and application state remain creator-owned. The SMTP service necessarily receives recipient addresses and links under the creator's agreement, not through the licensor.

## Trust boundaries

Browsers cannot set administrator status, verified timestamps, session generations, invoice totals, fee rates, settlement status or entitlements. General public content is independent of email verification. Restricted/unclassified/draft material fails closed for members. Paid bodies require an unexpired, unrevoked entitlement; account-session revocation also applies to those routes.

The login event stamps the authenticated snapshot's user ID and generation. Middleware rechecks the current database version rather than upgrading stale sessions. Existing sessions without the new stamp are rejected on upgrade. Password reset updates hash, generation, timestamp and remember-token state in the same transaction that consumes the reset token. A request already past the check cannot be retroactively recalled.

Verification links bind user ID, email hash, expiry and generation using a relative signature plus configured-host validation. Reset links contain only the token, not an email query parameter. Both use a validated canonical APP_URL, never the incoming host. Recovery requests enqueue encrypted jobs for known and unknown addresses without a public lookup. Mail work requested before the most recent password reset or more than 15 minutes ago is discarded. SMTP failure during issuance rolls the token transaction back; accepted email and database commit cannot be one distributed transaction, so a failed commit may require a new request.

Queue jobs are encrypted with APP_KEY. Raw failed-job persistence is disabled. The user table gains email_verified_at, password_changed_at and auth_version; reset tokens and jobs use separate creator-local tables. Worker and retention settings, configuration examples, failure behavior and migration rollback are documented in iterations/00.05.00.md.

InvoiceService still locks buyer/post, snapshots bounded integer totals and enforces unique buyer/idempotency keys. These are application invariants, not protection against an owner with direct database access. Quotes cannot grant entitlements, and no blockchain integration or central licensing service exists in this preview.

## Amount contract

Six decimals remain a TEST convention, not a production token choice. Decimal input is bounded at 1,000,000 TEST, rejects ambiguous/negative/exponent/overprecision forms, and requires 64-bit PHP. Zero is free; minimum paid value is 0.000050 TEST. For integer gross G, fee=floor(G*200/10000); creator=G-fee. Final tax base, refunds, gas payer and production precision are undecided.

## Route contract

Existing routes remain: GET / and /posts/{id}; GET/POST /register and /login; POST /logout; GET /account; POST /posts/{id}/invoices; GET /invoices/{uuid}; POST /checkout always 503; GET/POST /report; administrator /studio publishing and report inbox. GET /up is liveness only.

New routes: GET/POST /forgot-password; GET/POST /reset-password; authenticated GET /account/security; authenticated POST /email/verification-notification; signed authenticated GET /email/verify/{id}/{hash}/{generation}. Writes remain CSRF protected and recovery/reset/resend are throttled. Disabled account mail returns explicit unavailability rather than fake success. Test-only fixtures do not add production routes.

## Logs, evidence and compatibility

Global headers and the exception finalizer provide private/no-store, script-restricting CSP, no-referrer and server-generated request IDs. JSON logs keep approved names and typed IDs, replacing arbitrary context and exception text. Static-file behavior, proxy trust, access-log query stripping and SMTP provider logs require deployment review.

Version 00.05.00 adds account behavior to unreleased 00.04.00. No existing package or fee arithmetic changed. Old sessions must sign in again; additive database schema requires migration. Prefer retaining new columns on code rollback. Never restore old password hashes or revoked sessions when reverting to code without generation enforcement.

The gate rejects above-Low/unknown findings and incomplete current evidence. It is not branch protection, signed external approval or an independent audit. Required independent, secret-history, deployment-image and complete-M01 assessments remain pending. No release follows from passing tests alone.

## Primary implementation references

https://laravel.com/docs/13.x/authentication
https://laravel.com/docs/13.x/passwords
https://laravel.com/docs/13.x/verification
https://laravel.com/docs/13.x/queues
https://laravel.com/docs/13.x/errors
https://laravel.com/docs/13.x/logging

These describe framework behavior, not legal clearance or a production security certification.
