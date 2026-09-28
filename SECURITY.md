# Security policy

## Supported status

This repository contains an unreleased development preview. No version is represented as production-approved. Security fixes are developed against the active candidate and reviewed before integration. See [the security review](docs/SECURITY.md) for controls and explicit limitations.

## Report privately

Do not submit vulnerability details in public issues, discussions, pull requests or logs. If the repository's Security tab offers **Report a vulnerability**, use that private reporting channel. Include the affected commit/version, impact and minimal reproduction using synthetic data. Never submit real customer records, private media, credentials, session cookies or wallet private keys.

Private vulnerability reporting is a separate repository setting; this document does not enable it or guarantee that the button is available. If it is unavailable, request that @paulkakell enable a private reporting channel **without publishing technical details**. Do not assume an unverified email address is a security contact. No response-time or bounty commitment is implied.

## Release and dependency policy

Every unresolved finding above Low (Moderate/Medium, High or Critical) blocks release. Unknown severity, failed/missing/stale/wrong-candidate evidence and incomplete required reviews also block. Accepted, deferred or snoozed findings are not fixes. A false-positive determination needs documented evidence and a reviewer; findings must not be downgraded simply to pass.

Dependabot PRs require code review and the applicable validation/security checks. Development-only dependencies are not exempt. We do not automatically dismiss alerts, approve dependency PRs, merge updates, or authorize a release. See [repository administration](docs/REPOSITORY_ADMINISTRATION.md) for the distinction between version-update configuration and native GitHub security settings.

Passing a targeted source check or dependency audit is not an independent security assessment or proof that no vulnerabilities exist.
