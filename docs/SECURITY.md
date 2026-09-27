# Security review: 00.05.00

Development review, not security certification. Reference #7; baseline 00.04.00 and security/THREAT_MODEL.md. Full M01 acceptance and independent assessments remain incomplete. No above-Low exception is approved.

## Account controls

Authentication retains framework hashing, rotated login/signup sessions, CSRF and request throttling. Role assignment and security timestamps/generations are not public-fillable. Local administrator provisioning refuses implicit promotion. Password byte limits and null-byte validation remain in place.

Account recovery uses identical public responses and encrypted queued requests without checking whether an address exists on that HTTP path. This is a recovery-endpoint property, not a guarantee that all other application paths conceal account existence; existing signup uniqueness behavior remains part of the independent account review. Input validation failures and temporary infrastructure failures are generic within their categories. Reset attempts are throttled; malformed/expired/reused tokens fail. The framework broker stores token hashes, while the controller accepts only bounded hex tokens and validated passwords.

Password/token consumption and generation increment are transactional. User-row serialization coordinates issuance/redemption on PostgreSQL. Concurrent behavior is a required test on both supported databases, not an assumption derived from sequential tests. AccountSession rejects old/missing/mismatched stamps and refreshes the current user for each authenticated web request. Sessions already executing cannot be recalled. Resetting an administrator password is not a replacement for MFA; MFA and recovery-code policy remain release requirements.

Verification binds ID, email hash, expiry and session generation. The authenticated user must match. A used link is idempotent; a changed email/generation, expired link or tampered signature is rejected. Verification does not authorize content, assign roles, establish age or verify performers.

## Mail and private data

Mail is disabled by default. Enabling it requires canonical APP_URL, a configured database queue, disabled raw failed-job storage, and implicit-TLS SMTP outside local loopback. Unsupported or log mailers are rejected outside test exceptions. Link origins never use the supplied Host header; enabled account email enforces the configured hostname. TLS certificate verification remains enabled. Proxy/scheme enforcement and operational SMTP-provider approval require deployment-specific evidence.

Jobs contain encrypted recipient/purpose/timestamp data using the creator's APP_KEY. Reset tokens are hashed. Credentials and mail bodies are never intentionally added to application log context. New allowlisted events carry status and permitted IDs only. Tests cover template rendering, encrypted database jobs, worker expiry, queue failures, token rollback on delivery failure and synthetic SMTP delivery in a loopback-only sink. Production inbox delivery, deliverability, real provider TLS and independent privacy review are separate checks.

The SMTP provider receives recipient addresses and action links. Creator web-server access logs must omit query strings on account-link paths; no-referrer/no-store helps browser disclosure but does not sanitize infrastructure logs. The configured logger does not cover custom channels, bootstrap failure output, worker-supervisor output or third-party provider logs. Retention/alerts and comprehensive privacy lifecycle remain unfinished.

## Retained controls

Administrator routes require explicit true; paid bodies, drafts, classifications, owner-only quotes and report inbox remain protected. The guarded Entitlement model is not made mass-assignable for tests; fixtures explicitly populate it. Templates escape content. CSP restricts scripts/third parties, and security headers cover application/error responses. No media upload, remote user-selected URL fetch, wallet execution, callback settlement, fan balances or automatic debit exists.

The 200-basis-point fee remains exact TEST arithmetic. Quotes do not grant access. Self-hosted owners can change code/checkout; no claim of unbypassable licensing is made. No content, previews or complaints are transmitted to a licensor service.

## Release policy and evidence

Unresolved Medium, High and Critical findings block release. Unknown severity and incomplete/failed/empty/stale/wrong-candidate evidence block pending assessment. Accepted/deferred is not remediation. Register closures require named review and current verification; raw scanner findings above Low cannot be waived through that register. The default gate requires complete reviews and --development always reports release_allowed=false.

Composer advisories are time-specific. PHPStan/Larastan, Pint and PHP syntax are separate checks. Six project-specific Semgrep rules and the existing Python pattern checks are narrow, not comprehensive SAST or penetration testing. Scanning engines/actions are pinned; source scans run offline with read-only input. Broader maintained coverage, independent authentication/security assessment, repository-history secrets and actual deployment-image review remain blocking. The full account milestone also remains incomplete.

CI uses repository-read-only permission and retains allowlisted JUnit, dependency and normalized scanner summaries for 30 days. New SMTP test messages and application/server logs are excluded. Inventory is not a complete SBOM; these files are not signed provenance or durable release attestations. Public synthetic test values are not deployment credentials.

Branch/tag protections, trusted external approvals and production deployment enforcement are not configured. This gate is a CLI/CI result, not a repository-administrator permission barrier. Production approval must bind externally controlled evidence to the final candidate.

## Rollback and outstanding product work

The new migration preserves users/content on its own rollback but removes account timestamps, queued mail and reset tokens. Stop workers, retain the latest password hashes and APP_KEY, and revoke all sessions before reverting to a pre-generation version. Never restore revoked sessions or obsolete passwords from a backup merely to pass rollback tests. See iterations/00.05.00.md for examples.

MFA, device/session controls, privacy lifecycle, operational readiness, accessibility and deployment hardening remain unfinished. Content verification/consent/age assurance/removal workflows, payment and sanctions analysis, refunds and final licensing terms still need implementation and professional review. No main merge, production release, funds transfer or restricted-content enablement is approved by this document.
