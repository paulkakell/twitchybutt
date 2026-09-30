# Architecture: 00.07.00

Unreleased increment on the 01.00.00 delivery track, issue #9. One creator business per installation. The integration branch name is a goal, not release approval. Earlier architecture and implementation records remain in Git history and versioned iteration documents.

```text
Browser -> origin/security headers -> current session registry/generation
        -> MFA boundary -> route authorization -> creator-owned SQL database
           |-> classified posts / immutable TEST quotes / entitlements / reports
           |-> encrypted MFA keys / hashed recovery codes / HMAC attempt budgets
           |-> private media metadata / atomic storage reservation ledger
Creator account-mail worker -> encrypted jobs -> creator SMTP service
Creator media worker -> private quarantine -> GD/FFmpeg -> private derivatives
Authorized browser -> expiring media route -> current post/access check -> derivative
Exact commit -> SQLite/PostgreSQL tests + scoped scans -> evidence -> release gate
```

Laravel 13 and Blade provide sessions, CSRF, routing, validation and escaped templates. SQLite and PostgreSQL are the tested database paths. No content, previews, reports, identity documents or credentials are transferred to the licensor. The creator's SMTP provider receives recipient addresses and account links under the creator's contract. There is no blockchain execution, central licensing API or stored fan balance in this version.

## Accounts and authorization

Administrator authority requires an explicit role and current MFA completion. Enrolled members must also complete MFA before authenticated content access. Password-only enrolled sessions can use only the allowed challenge/logout/recovery routes. Classification is checked before member entitlements, and administrator private-post access uses the same MFA-aware gate as the studio.

The session registry stores management UUIDs, account/generation IDs and UTC timestamps, not bearer cookies, IP addresses or fingerprints. Requests reject revoked, expired, missing or cross-account registry references. Login stamps the authenticated snapshot rather than adopting a newer generation after a concurrent reset. MFA confirmation checks the expected generation before starting a replacement session. Already-running responses cannot be recalled.

TOTP uses RFC 6238 SHA-1, six digits and 30-second steps with adjacent-step tolerance. Keys are encrypted under APP_KEY; a monotonic consumed counter prevents reuse. Ten recovery codes each contain 128 random bits and are stored as hashes. Factor replacement requires password confirmation and MFA completed within five minutes; pending material is generation-bound. The old factor remains active until replacement confirmation, which invalidates previous recovery codes and other sessions. Password recovery preserves MFA.

Critical writes consume ordered transactional SQL attempt budgets. HMAC identifiers avoid raw email/IP storage but remain pseudonymous security data. IP-first checks bound account-key row allocation, and downstream refusal still consumes the earlier IP budget. Transactions retry only after rollback, at most five times. This is not network denial-of-service protection.

## Private media

MFA-authorized administrators attach supported images or optional MP4 files to posts. Source files enter creator-local private quarantine, outside public/. Generated UUID directories and fixed filenames reject traversal and symlinks. A singleton SQL ledger conditionally reserves input plus maximum derivative capacity before filesystem writes; it serializes competing uploads without a SQLite read-to-write lock upgrade.

A separate queue claims each asset with a processing token. GD reencodes bounded images to JPEG and thumbnails; FFmpeg creates one H.264/AAC MP4 rendition and a JPEG poster. Native video subprocesses do not inherit application credentials. Sources are never delivered and are removed after successful conversion. Native-decoder OS/container isolation remains a release requirement, not an implemented property of this queue.

Only ready derivatives appear in authorized post HTML. Five-minute relative signed URLs are bound to the post and public/user-generation subject; every request rechecks current classification and entitlement. Revoking access, unpublishing, or deleting blocks subsequent delivery even before signature expiry. Range requests use BinaryFileResponse, correct MIME and private/no-store headers. There is no generic public filesystem serving route.

Deletion first marks the asset unavailable, then removes local files, then releases its reservation. Failed cleanup remains unavailable and keeps capacity reserved. Processing/uploading states cannot be deleted concurrently. Recovery of abandoned operations, cloud storage, resumable uploads, isolated inspection, adaptive streaming, captions and watermarks remain incomplete. External downloads and backups cannot be recalled by local deletion.

## Amounts, logs and release

TEST quotes retain six decimal places and bounded 64-bit integers. The licensing share is floor(gross*200/10000), and creator share is gross minus fee. A 20 TEST quote allocates 0.400000 and 19.600000; it neither transfers funds nor grants access. Production asset selection, settlement, taxes and refunds remain separate requirements.

Known-event JSON logs retain only approved identifiers. Early bootstrap failures return a fixed 503 and constant diagnostic event rather than exception details. Infrastructure logs, SMTP records, native-parser isolation and operational alerting need separate assessment.

See iterations/00.07.00.md for settings/routes/examples, SECURITY.md for assessment limits and ONE_ZERO_ACCEPTANCE.md for remaining product work. Tests and scoped scans are not release approval. No unresolved above-Low or unknown finding, incomplete required review, or missing/current-candidate evidence may pass the release gate.
