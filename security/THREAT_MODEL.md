# Authorization and data boundary review: 00.04.00

Scope: single-creator development preview; reference #5, TB-013 and TB-016. This is an engineering threat model, not an independent penetration test. Requirements remain content-neutral and self-hosted. No live funds, media processing or central content service is implemented.

## Assets and trust boundaries

Fans' credentials and sessions, creator drafts, paid post bodies, invoices, reports and local logs belong to the site operator. A browser is untrusted, including hidden fields, role/price/status claims, request IDs and callback payloads. A signed-in member is not an administrator. A quote is not a payment. An entitlement is not age verification. The creator controls their server and can replace the software: fee enforcement cannot defeat that owner.

| Boundary | Threat | Control and regression evidence |
| --- | --- | --- |
| Anonymous to member | Role injection, weak credentials, fixation | Explicit registration fields; password validation/hash; session rotation; existing CmsTest and HardeningTest. |
| Member to operator | Direct URL access, forged updates, report disclosure | auth plus manage-content policy; controller authorization; SecurityRegressionTest tests all operator reads and updates. |
| Viewer to private post | Draft/title leak; unpaid body exposure; expired access | PostPolicy and response filtering; existing paid/draft/expired/revoked/restricted regression tests. |
| Buyer to invoice | IDOR, browser price overrides, duplicate request | Buyer-only retrieval and server-side immutable quote snapshots; existing invoice ownership/idempotency tests. |
| Application to local log | Private messages, nested secrets, exception traces | Deny-by-default Monolog processor; typed identifier allowlist; real error/report paths and emitted JSON tests. |
| Request to response | Error pages missing security headers or cache policy | Global middleware plus exception response finalizer; unknown routes, health, 403, 422, 500 and 503 tests. |
| Code to release | Green unit tests mistaken for security approval | Separate scan gate and release-readiness job; unknown severity/missing/stale evidence blocks. |

## Residual work and release exclusions

MFA, verified email, password recovery/session revocation, complete content safeguards, live payment verification, trusted-host/proxy deployment checks, full secret-history and runtime image scans, browser accessibility, concurrent loads and independent assessment remain unfinished. These are release blockers or unfinished controls, not declarations that a vulnerability has been found. Do not assign an artificially low severity to get a green build.

The source-scanning rules are narrowly scoped and cannot prove absence of vulnerabilities. Redaction covers the configured CMS daily channel; external web-server access logs, infrastructure logs, custom channels and failures before the logging stack is available need deployment review. No secret was found in a live user record and no breach is claimed by these hardening changes.

Only local, synthetic fixtures enter tests. Do not upload report bodies, media, tokens, seed phrases or production credentials to GitHub, Actions artifacts or Drive.
