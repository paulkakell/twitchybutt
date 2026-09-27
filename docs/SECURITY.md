# Security review: 00.06.00

Development engineering review, not an independent audit or certification. Issue #9. The complete product and M01 acceptance remain unfinished. No above-Low exception is authorized.

## Account and authorization controls

Current-password confirmation protects enrollment and session changes. TOTP enrollment expires after ten minutes and requires proof of the new factor; unconfirmed keys grant no authority. Confirmed keys use framework authenticated encryption and are hidden from model serialization. High-entropy recovery codes are shown once, hashed at rest and consumed transactionally. Time-step reuse is rejected across sessions. RFC vectors, malformed values, ownership, expiry and competing consumption are tested.

A password-only login does not grant authenticated content access for an enrolled user. Administrators need MFA both at the studio middleware and content-authorization gate. Missing confirmed key material does not silently turn MFA off. Password resets preserve MFA. Lost-authenticator replacement requires a recently completed MFA challenge (including a saved recovery code) plus password confirmation. Existing factors remain active until replacement confirmation. Pending state is generation-bound, so a later password reset invalidates it. Losing every factor and recovery code still requires an independently reviewed operator procedure; no email-only bypass exists.

Each authenticated request checks its user/generation stamp, registry ownership, revocation, idle deadline and absolute deadline. Revoke-all rotates the generation. Session UUIDs are management references, not bearer tokens. Current requests already executing cannot be recalled. Login/enrollment/reset races are constrained by snapshots and database transactions; independent concurrency review remains required.

## Attempt budgets and private state

Authentication, recovery, MFA, verification and other public writes use serialized SQL attempt budgets, not a read-then-increment filesystem counter. IP-first ordering prevents blocked sources creating unlimited account rows. Rejected later scopes still consume earlier limits. Keys are HMACs under APP_KEY, not raw emails/IPs. Synchronized multi-process checks require exactly five of ten permitted operations. HMAC identifiers remain pseudonymous security data, not anonymous data. Daily pruning deletes expired budgets and old session metadata.

CSRF, escaping, security headers and no-store/no-referrer remain enabled. Passwords, action tokens, factor keys and codes are excluded from flashed input. Known-event logging drops arbitrary messages/context and traces. Setup secrets and recovery codes never go in URLs or external QR requests. Backup, server/proxy access-log, worker-supervisor and SMTP-provider privacy remain creator deployment responsibilities. No external recipient is used by the loopback SMTP tests.

## Security checks and release refusal

The configured pipeline runs dependency advisory checks, PHP syntax/Pint/PHPStan, six custom Semgrep rules, security-policy regressions, full application tests, fresh builds, migration checks and real HTTP/concurrency tests. The custom source rules are limited; zero findings is not complete clearance. Dependencies are locked and unchanged in this increment. Tool/action identities are pinned, but hosted infrastructure is not a byte-reproducibility claim.

Unresolved Medium, High, Critical and unknown-severity findings block release. Missing, failed, empty, stale or wrong-candidate evidence and required incomplete reviews also block. Accepted/deferred is not fixed. Development mode never authorizes release. Required independent security, repository-history secret scanning, deployment-image assessment and complete M01 approval remain pending. Branch/tag protection, trusted external approval enforcement and a production release pipeline are not configured; repository administrators are not constrained by this CLI policy alone.

An attempted Chromium journey was blocked by runtime administrator policy. Browser/accessibility validation remains unverified; no browser-policy bypass was attempted. Real provider TLS/delivery, full load tests, secret-history/image scans and independent penetration testing remain requirements. The known upstream artifact downloader Buffer deprecation warning remains documented for review.

## Compatibility and rollback

Apply both additive security migrations before activation. Existing sessions must sign in again. Rolling back the MFA schema destroys factor/recovery/session state, so it is tested only on disposable databases. Prefer a forward fix. Do not expose pre-MFA code as an emergency bypass; maintain access restrictions, preserve current passwords and APP_KEY, invalidate all sessions and obtain security review first.

No wallet transaction, restricted-content launch, media processing or final license approval is implemented by this increment. See [threat model](../security/THREAT_MODEL.md), [iteration](iterations/00.06.00.md) and [1.0 acceptance](ONE_ZERO_ACCEPTANCE.md).
