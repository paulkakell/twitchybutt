# Security review: 00.07.00

Engineering controls and limits, issue #9. This is not independent certification. All unresolved above-Low and unknown-severity findings, incomplete reviews or missing current-candidate evidence block release. Media and video default off; payments and restricted publication remain false-only.

## Authentication and authorization

Framework password hashing, session rotation, CSRF and transactional attempt budgets protect account writes. Public registration cannot choose roles, verification or generation fields. Passwords reject null bytes and values beyond bcrypt's 72-byte bound. Password recovery uses generic requests, encrypted queue jobs, hashed expiring one-use tokens and atomic generation changes. Existing sessions are revoked on their next request. Broader account-existence disclosure, lost-all-factor recovery and provider operations remain independent-review scope.

Administrators require explicit role plus completed MFA; enrolled members cannot use password-only sessions for authenticated content. TOTP counters and hashed recovery codes are consumed transactionally. Replacement is password-confirmed, recent-MFA-bound and generation-bound; old factor enforcement remains until confirmation. Password reset does not remove factors. Owner-only registry queries and revocation check session identity, generation, idle/absolute deadlines and current database state.

Post classification precedes member entitlement. Private/draft/restricted titles and paid bodies do not leak through public catalogue responses. Studio, invoices, reporting and media routes enforce role/ownership/access checks. Plain text is escaped. No arbitrary HTML, remote media fetch, public file-serving route, wallet signing or fan balance exists.

## Media and untrusted parsing

Uploads require MFA administrator authority, CSRF, actual-byte MIME inspection, bounded size, alternative text and per-post/storage limits. Private generated paths reject traversal/symlinks and enforce 0700/0600 permissions. The atomic storage reservation happens before filesystem writes and is tested under ten competing processes.

Only a claimed queued asset may become ready. GD/FFmpeg reencode source data and remove originals after successful conversion. Native video commands use argument arrays, cleared environment, restricted protocols/external references and dimension/duration/output/thread/time limits. No shell command is assembled from a client filename. Neither reencoding nor a separate queue constitutes malware certification or native-parser containment. Isolated workers, patched runtime images, resource enforcement and crash recovery remain release requirements.

Delivery accepts only ready derivatives with valid short-lived relative signatures, matching subject and current authorization. Paid/unauthorized HTML omits media URLs and descriptions. Revocation, expiry, classification changes and deletion invalidate subsequent access. Responses use explicit MIME, nosniff, private/no-store and bounded range handling; no client-specified sendfile delegation. Existing downloads, browser buffers and external backups cannot be recalled.

Deletion revokes state before cleanup and keeps quota reserved on failure. Active upload/processing deletion is refused. Claim-token fencing prevents a duplicate job failure from corrupting another worker's state. Abandoned operations require reviewed recovery; do not reset quota or edit ready state manually.

## Logs, startup and configuration

Daily JSON logs allow known events and typed IDs only; arbitrary messages/context/extra fields and exception class names are redacted. Mail jobs are encrypted and raw failed-job storage is disabled. Passwords, tokens, MFA codes and keys are excluded from flashed input. Media processes do not inherit application environment secrets. Infrastructure access logs, supervisor output, SMTP records and backup retention still require independent controls.

Recovery testing found that early exceptions could break logging and activate diagnostic output. The public entrypoint suppresses engine display and uses a fixed 503 fallback. The exception renderer now returns a fixed 503 while application bootstrap is incomplete, including invalid production-debug and unsupported payment configuration. Five subprocess/real-HTTP regression tests cover missing autoload, malformed source, early throw and rejected configuration. These tests do not certify all host/web-server error paths.

No APP_KEY/SMTP/wallet secret is committed. CI credentials are synthetic public fixtures. Secure cookies/debug checks and canonical origins fail unsafe configurations, but document-root/proxy/TLS/permissions/host lifecycle need complete deployment tests. No public operational release is approved.

## Evidence and release

Composer audit checks current published advisories for the locked packages. Pint, syntax checks and PHPStan/Larastan support code quality; six custom Semgrep rules and targeted Python checks provide limited source coverage, not a comprehensive assessment. Failed/missing/empty/stale/wrong-commit scan evidence is rejected. A clean scoped scan cannot prove no undiscovered vulnerabilities.

Independent application/contract review, repository-history secrets, actual runtime-image packages and complete product acceptance remain mandatory. Above-Low raw scanner findings cannot be waived through the manual register; accepted/deferred is not remediation. Current implementation retains sanitized artifacts for 30 days, not durable signed release provenance. Branch/tag protection, trusted external approvals and deployment enforcement remain unfinished.

See security/THREAT_MODEL.md, iterations/00.07.00.md and ONE_ZERO_ACCEPTANCE.md. Content-specific verification, consent, removal, legal/payment-role, provider and license reviews remain separate requirements. No real payment or restricted-content enablement is authorized by this review.
