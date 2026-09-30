# Change log

## 00.08.00 - Operator report case lifecycle (unreleased)

Classification: additive feature. Reference #9, roadmap TB-060. Adds creator-local report case states and guarded transitions for review, removal/rejection, appeal and closure. MFA-authorized administrators can record bounded operator notes; review/resolution timestamps and allowlisted structured transition logs provide an audit trail without copying report text into logs.

Adds one reversible reports-table migration and feature regressions for authorization, state progression and invalid transitions. No dependency, payment, token, media, restricted-publication or public API enablement. Qualified jurisdiction/policy review, external deadlines/notifications, provider verification and the rest of M05 remain incomplete. Restricted publishing stays false-only.

Validation requirement: full SQLite/PostgreSQL application suite, migrations forward/backward, Pint, PHPStan, Composer audit, repository/security/roadmap suites, fresh locked/no-dev builds, HTTP/concurrency/performance smoke, and current security checks on the exact candidate. Security findings above Low remain release-blocking. Rollback: stop writes, preserve reports, revert application code, then run the migration down only after exporting any new case metadata that must be retained.


## 00.07.02 - Main integration metadata (unreleased)

Classification: fix and additive documentation. References #9, PR #10 and PR #16. The owner requested "Skip the reviews and merge into main". Prepare the cumulative development branch for that source integration without representing pending assessments as completed or approving production use.

Increment VERSION and the release-policy version from 00.07.01 to 00.07.02. Correct README branch/status instructions and add integration, compatibility, validation and rollback notes. Preserve the earlier CodeQL contact-link remediation on main (`2d2684991ee2ee39ae2aeb9470c15903ca2327e6`) and the development baseline (`e433576fc50900d105512e1b0e443f371668ab4d`).

No runtime implementation, API, dependency, lockfile, schema, feature-default, scanner rule or release-threshold change relative to the development baseline. All four production review records remain pending. Skipping reviews for this merge does not supply missing security evidence. No release tag or production artifact is issued. Existing application/security/repository suites must run on the integration candidate; results belong to their exact commit and are recorded in PR #10.

## 00.07.01 - Media validation repair (unreleased)

Classification: fix. Reference #9. Baseline 00.07.00 at 21b6757e022b2cfc78ddb997410e82ad5a6e78da. Explicitly install GD/FFmpeg on disposable CI runners and record package/tool versions; the earlier runner omitted required tools and skipped application execution. Keep all prerequisite assertions instead of skipping media tests.

Correct the subprocess-environment test to create and restore its own synthetic library-path fixture instead of assuming a portable local runtime. Add actual child-process environment checks with present and absent library overrides, including getenv, server and environment secret sources. No secret values or subprocess environment output are retained as artifacts.

No application dependency, schema, fee or feature-default change. Existing private-media, MFA and access boundaries remain enforced. Update current documentation and record failed runs by commit. Full SQLite/PostgreSQL, SMTP/HTTP/MFA/media/concurrency, fresh build, style/type and security checks are required on the final candidate. Above-Low findings and incomplete security reviews still prohibit release.


## Earlier increments

[Complete change-log history through 00.07.00](docs/changelog/through-00.07.00.md) is preserved without editing earlier entries. No prior release or security approval is implied.
