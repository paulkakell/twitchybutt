# Trust-boundary review: 00.07.00

Single-creator development preview; issue #9. This is an engineering threat model, not independent clearance. Creator accounts, content, reports, SMTP credentials and factor keys stay on creator-owned systems except configured mail delivery.

| Asset or boundary | Threat | Control and adversarial evidence |
| --- | --- | --- |
| Administrator content operations | Role injection or password-only access | Registration allowlist; explicit role plus MFA gate; direct-route and private-draft regression tests. |
| Paid/private content | Cross-account or expired entitlement | Server authorization, classification before entitlement, omitted paid bodies; ownership/revocation tests. No payment-success shortcut. |
| MFA enrollment and replacement | Stolen session/password installing a factor | Password confirmation; new-factor proof; replacement needs MFA within five minutes; pending generation binding. Old factor remains enforced until confirmation. |
| TOTP and recovery proof | Replay, parallel use, weak/random tokens | RFC vectors, monotonic step, row-locked single-use recovery deletion, 128-bit random recovery codes, competing-process tests. Independent cryptographic review pending. |
| Session credentials | Fixation, stale authority, another user's session | Real Login stamping, cookie rotation, user/generation/registry checks, owner-only revocation, idle/absolute deadlines; separate-client HTTP checks. |
| Password recovery | Enumeration, replay, MFA downgrade | Generic enqueue path, expiring hashed token, serialized redemption, generation rotation and preserved MFA. Registration enumeration remains separate review scope. |
| Attempt counters | Parallel requests exceed read-then-write limit | Atomic SQL budgets, HMAC identifiers, IP-first allocation, bounded transaction retries, ten-worker barrier tests. Network-level denial-of-service remains outside this control. |
| Logs, old input and mail jobs | Secret or private-content leakage | Allowlisted structured events, exclusion of passwords/tokens/codes from flash, encrypted mail jobs, no raw failed-job persistence; sentinel regression tests. Infrastructure logging remains separate. |
| Database/APP_KEY/backup | Operator compromise or stale restore | Creator-controlled access, encrypted factor material, documented restore/rollback limits. A server owner can modify software; no tamper-proof fee claim. |
| Checkout and restricted publication | Unsupported flags bypass unfinished safeguards | False-only startup guards, TEST quotes do not settle; restricted/unclassified drafts stay private. |
| Release evidence | Missing/stale/scanner waiver mistaken for approval | Exact-candidate policy rejects above-Low/unknown findings and incomplete reviews. CLI/CI checks do not substitute for repository permissions or signed external approvals. |

Data minimization: the session registry stores management IDs and timestamps, not raw user agents/IPs/cookies. Rate-limit HMACs are pseudonymous and expire. Daily security pruning clears obsolete metadata and pending, never confirmed, factors. SMTP providers receive action-link messages under creator contracts. No central content/identity-document pipeline is introduced.

Residual release requirements include all-factor-loss recovery, independent authentication/concurrency assessment, maintained broader SAST, secret-history and runtime-image scans, configuration/proxy/TLS review, browser/accessibility testing, operational alerts and proven restore procedures. No Medium-or-higher risk acceptance is granted. See [security review](../docs/SECURITY.md) and [iteration](../docs/iterations/00.06.00.md).


## 00.07.00 additional media threats

Private assets include originals, derivatives, descriptions and storage reservations. Adversarial inputs include spoofed MIME/extensions, oversized/pixel-bomb media, malformed tracks, path/symlink traversal, copied/expired URLs, revoked entitlements, cross-post writes, duplicate workers and interrupted deletion. Current controls are documented in docs/iterations/00.07.00.md and tested in PrivateMediaTest plus real HTTP queue checks. Native parser compromise requires OS isolation and patching beyond these application checks. The creator owns all processing; no media travels to a licensor service. Restoring old file/database state can resurrect deleted data and needs an independently reviewed recovery process.
