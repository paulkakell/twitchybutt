# Security review: 00.03.00

This document records design controls and review limits, not a security certification.

Authentication uses framework hashing and sessions, rotates sessions on login/signup, invalidates on logout, enforces CSRF and throttles login/signup. Roles cannot be assigned by public registration. CLI administrator provisioning requires local server access and refuses duplicate email. Known launch blockers: no email verification, account recovery or MFA; password and session policies require further independent review before real accounts.

Authorization covers studio routes, owner-only invoice retrieval, classification-before-entitlement access, expiry/revocation, and private report viewing. The public catalogue never includes paid body text. Templates escape user content; CSP forbids scripts and third-party resources. No media upload path, SSRF fetcher, webhook, wallet execution, fan balance or automatic debit exists.

Money calculations use bounded integer units and a fixed 200-basis-point constant; browser amounts are ignored. Quote idempotency and buyer locking avoid duplicate snapshots. Invoice immutability is an ORM invariant only: a database operator or direct query can bypass it. There is no signed external invoice, verified payment event or blockchain contract yet. Paid quotes never create entitlements. Fee enforcement on modified self-hosted installations remains contractual, not technically unavoidable.

Logs retain event names, internal IDs and generated request IDs. Report bodies, post bodies, passwords, emails and wallet keys are not added to log context. Exception message/trace logging is replaced with exception type only. Site database, sessions, logs and backups stay creator-owned. No telemetry or content transfer to a central service is implemented.

`composer audit --locked` checks published package advisories at execution time. PHPStan/Larastan provides type/static checks; Pint and PHP lint check style/syntax. `scripts/security_scan.py` is explicitly a small targeted source-pattern check, not comprehensive SAST or a penetration test. Its absence of findings must not be described as proof of security. CI operating-system/runtime/container supply-chain review and independent SAST remain additional production gates.

Only the initial lock-generation job can write repository contents, and only the hard-coded build branch is pushed by its script. It does not run on pull requests. Read-only validation checks the exact emitted commit. Actions are pinned to a reviewed checkout commit. PostgreSQL's CI image is major-tagged, not digest-pinned; no claim of byte-reproducible infrastructure is made. No deployment credentials or custom repository secrets are needed.

No legal advice is embedded as a runtime exemption. Adult-capable verification, consent records, age assurance, jurisdiction rules, takedown deadlines, sanctions/payment-role review and final license terms remain unresolved integration/review gates. Do not use this preview for public restricted-content operations.
