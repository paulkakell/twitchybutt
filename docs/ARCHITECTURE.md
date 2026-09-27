# Architecture: 00.06.00

Unreleased account/security increment on the 01.00.00 delivery track, issue #9. Single creator/business per installation. The branch name is a goal, not release approval. Historical implementation notes remain in docs/iterations/00.03.00, 00.04.00 and 00.05.00 documents where present and in Git history.

```text
Browser -> origin/security headers -> private session -> current generation + registry
        -> MFA boundary -> route authorization -> creator-owned SQL database
                             |-> classified posts / immutable TEST quotes / entitlements
                             |-> private reports
                             |-> encrypted MFA keys / hashed recovery codes
                             |-> HMAC attempt budgets
Creator queue worker -> encrypted account-mail jobs -> creator SMTP service
Source commit -> locked SQLite/PostgreSQL validation + scoped security scans
              -> exact-commit evidence -> release gate (currently denied)
```

Laravel 13 and Blade handle routing, sessions, validation and escaped templates. SQLite and PostgreSQL are the supported database paths. No content, previews, reports or account credentials are sent to a licensor service. A configured SMTP provider receives recipient addresses and action links under the creator's contract. There is no media, blockchain execution, central licensing API or fan-balance implementation yet.

Administrator authority requires an explicit role plus current MFA completion. Enrolled members must also finish MFA before authenticated content access. Password-only sessions can use only challenge/logout/password-recovery routes. Post classification is checked before a member entitlement; forged restricted publications remain unavailable. Administrator private-post access uses the same MFA-aware gate.

The session registry contains management UUIDs, user/generation IDs and UTC timestamps, not authentication cookies, IP addresses or fingerprints. Server checks reject revoked, expired, missing and cross-account registry references. The real Login event stamps the authenticated snapshot; it must never adopt a newer generation after a concurrent password reset. MFA confirmation checks that its resulting generation is exactly the expected increment before creating a new session.

TOTP uses RFC 6238 SHA-1, six digits, 30-second steps and an adjacent-step tolerance. Confirmed keys are encrypted under APP_KEY. A monotonically increasing counter and row-locked recovery-code deletion prevent reuse. Ten recovery codes contain 128 random bits each and are stored as SHA-256 hashes. Replacement requires password plus MFA completed within five minutes, keeps the old factor active until confirmation and binds pending material to the authentication generation. Completion rotates all recovery codes and revokes other sessions. Password recovery never removes MFA.

Critical write limits use ordered transactional SQL budgets. HMAC keys avoid raw identity storage. IP-first checks bound account-row allocation; downstream refusal still consumes the IP budget. Transactions retry only after rollback, with a fixed five-attempt bound. Database failure denies access. This is application limiting, not network denial-of-service protection.

TEST amount arithmetic is unchanged: six decimal places, bounded 64-bit integers, fee=floor(gross*200/10000), creator=gross-fee. A 20 TEST quote has a 0.4 licensing share. Quotes neither transfer funds nor create entitlements. Actual token precision, taxes/refunds and payment finality remain unresolved product requirements.

See [iteration routes, settings, examples and migration precautions](iterations/00.06.00.md), [security review](SECURITY.md), and [1.0 acceptance](ONE_ZERO_ACCEPTANCE.md). Runtime logs allow only known events and typed identifiers; infrastructure logs require separate controls. There are no release approvals or protected deployment permissions implied by passing local tests.
