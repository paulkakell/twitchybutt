# Security review: 00.04.00 iteration 1

This records design controls and review limits, not a security certification. Reference #5. M01 remains incomplete and this version is not released.

Authentication uses framework hashing and sessions, rotates session IDs on login/signup, invalidates on logout, enforces CSRF and throttles login/signup. Public registration cannot assign roles. Local administrator provisioning refuses existing email accounts. Validation rejects email arrays, passwords exceeding bcrypt's 72-byte limit and null-byte passwords. Launch blockers include email verification, recovery, MFA and independent authentication review.

Authorization covers studio routes, owner-only invoices, classification before entitlement checks, expiry/revocation and private reports. The manage-content gate now requires an explicit true administrator value; absent/null role values deny access rather than throwing a return-type error. Additional route tests cover member access to operator pages and forged updates. The public catalogue omits paid bodies. Templates escape user content; CSP prohibits scripts and third-party resources. No media upload, remote URL fetch, webhook, wallet execution, fan balance or automatic debit exists.

Amounts require 64-bit PHP, bounded integers and a fixed 200-basis-point constant. Browser totals are ignored. Quote idempotency and buyer locking prevent duplicate snapshots. Invoice immutability is an ORM invariant, not protection against the database owner or direct SQL. There is no verified payment event, signed external invoice or blockchain contract. Quotes never create entitlements. Modified self-hosted checkout cannot be made technically incapable of bypassing a licensing fee.

The daily JSON logger permits only approved event names, positive integer actor/post/report IDs and validated UUID request/invoice IDs. Arbitrary messages become cms.log.redacted; other context, nested data and extra metadata are discarded. Exception events retain the constant Throwable rather than paths, messages or traces. Tests inject synthetic secrets, emails, report text and exception payloads through the real logging stack. This does not cover infrastructure access logs, custom logging channels or failures before the configured logger is available. State remains creator-owned; no telemetry or content transfer to a central service is implemented. Hosted metrics and alerts are not configured.

Global middleware and the exception response finalizer apply private/no-store, CSP, nosniff, frame protection, referrer policy and generated request IDs to application and error responses. HSTS is set for secure requests. Static files served outside Laravel require external web-server controls; trusted-host, proxy and document-root checks remain unfinished deployment work.

## Release is denied until evidence is complete

All unresolved Medium, High and Critical findings block release; unknown severity also blocks pending assessment. Accepted and deferred risks are not remediation. Current-commit evidence and a named reviewer are required for fixed or false-positive register closures. Raw scanner findings above Low cannot be suppressed through the manual register.

The security gate also rejects failed/missing/empty scans, unsupported report schemas, wrong commits/versions and evidence older than 24 hours. Unit tests exercise these negative cases. The default CLI mode requires independent security, secret-history, deployment-image and complete-M01 approvals. Those approvals remain pending. The separate release-readiness job fails until they exist. Development-mode scan success explicitly prints release_allowed=false and is never release approval.

Composer audit checks published dependency advisories at execution time. PHPStan/Larastan provides static/type analysis, Pint checks style, and PHP lint checks syntax. The project-specific Semgrep configuration currently has six rules; both it and the older Python pattern scanner are limited checks, not comprehensive SAST or penetration testing. The Semgrep engine is digest-pinned, runs with a read-only source mount and no network, and normalized artifacts exclude source snippets. Broader maintained rules, secret-history, runtime image and independent review remain release gates.

The dependency lock is unchanged. Current validation has repository read permission only and cannot change source or dependency versions. Actions are commit-pinned; PostgreSQL and Semgrep images are digest-pinned. The hosted runner is controlled by GitHub, so this is not a claim of byte-reproducible infrastructure. CI passwords and the PHPUnit key are disposable public fixtures, not deployment credentials. Retained artifacts include JUnit, dependency audit/inventory/licenses and sanitized security evidence for 30 days; they exclude .env and application logs. This is not signed provenance or a complete SBOM.

GitHub branch/tag protections, signed external review approvals and deployment enforcement are not configured. The checker enforces its own CLI/CI result, not repository-administrator permissions. Do not describe it as impossible to bypass. A release pipeline must eventually consume reviewer-controlled approvals for the exact candidate; see docs/iterations/00.04.00.md.

Adult-content verification, consent records, age assurance, jurisdiction rules, removal deadlines, sanctions/payment-role analysis and final license terms remain integration and professional-review gates. Do not use this preview for public restricted-content operations or real payments.

See security/THREAT_MODEL.md for the threat-boundary table and docs/iterations/00.04.00.md for commands, compatibility, rollback and the remaining roadmap. No above-Low exception is approved here.
