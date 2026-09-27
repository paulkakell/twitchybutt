# Security review: 00.03.00

This records design controls and review limits, not a security certification.

Authentication uses framework hashing and sessions, rotates session IDs on login/signup, invalidates on logout, enforces CSRF and throttles login/signup. Public registration cannot assign roles. Local administrator provisioning refuses existing email accounts. Validation rejects email arrays, passwords exceeding bcrypt's 72-byte limit and null-byte passwords. Launch blockers include email verification, recovery, MFA and independent authentication review.

Authorization covers studio routes, owner-only invoices, classification before entitlement checks, expiry/revocation and private reports. The public catalogue omits paid bodies. Templates escape user content; CSP prohibits scripts and third-party resources. No media upload, remote URL fetch, webhook, wallet execution, fan balance or automatic debit exists.

Amounts require 64-bit PHP, bounded integers and a fixed 200-basis-point constant. Browser totals are ignored. Quote idempotency and buyer locking prevent duplicate snapshots. Invoice immutability is an ORM invariant, not protection against the database owner or direct SQL. There is no verified payment event, signed external invoice or blockchain contract. Quotes never create entitlements. Modified self-hosted checkout cannot be made technically incapable of bypassing a licensing fee.

Logs contain event names, internal IDs and generated request IDs. Post/report bodies, passwords, email addresses and wallet keys are not included in log context. Exception reporting retains only the exception type. State remains creator-owned; no telemetry or content transfer to a central service is implemented. Hosted metrics and alerts are not configured.

Composer audit checks published dependency advisories when it runs. PHPStan/Larastan provides static/type analysis, Pint checks style, and PHP lint checks syntax. The Python source-pattern scanner is deliberately narrow and is not comprehensive SAST or a penetration test. Independent SAST and operating-system/container vulnerability review remain additional production gates.

The dependency lock is committed. Historical write-enabled bootstrap jobs were removed; current validation has repository read permission only and cannot change source or dependency versions. Checkout is pinned to a Node24 action commit. PostgreSQL's CI image is digest-pinned. The hosted runner is controlled by GitHub, so this is not a claim of byte-reproducible infrastructure. CI passwords and the PHPUnit encryption key are public disposable test fixtures, not deployment credentials.

Adult-content verification, consent records, age assurance, jurisdiction rules, removal deadlines, sanctions/payment-role analysis and final license terms remain integration and professional-review gates. Do not use this preview for public restricted-content operations.
