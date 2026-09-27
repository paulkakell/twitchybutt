# Roadmap documentation revision 00.03.01

Classification: additive documentation and contributor tooling. Fixes the absence of a maintainable product backlog. References issue #3; based on application PR #2 and commit b14dc4cf32e693808dcd326496db5336b7721399.

Added: live native Google Sheets roadmap in the designated Drive folder; 100 items, ten milestones, 12 decisions, an ideas inbox, 16 release controls, formula summary, native dropdown tables, stable identifiers, a compact GitHub snapshot, feature-request templates and 13 standard-library validation tests.

No application behavior, package version/lock, database migration, secret, payment or deployment changes. Root VERSION remains 00.03.00; docs/roadmap/VERSION is 00.03.01. Milestone dates and efforts are uncommitted. Eight baseline items are implemented in an unmerged preview; none is marked Released.

Validation: local roadmap structure and 13 regression tests passed before publication. Native Sheet counts and formula values were read back, including 100 items, eight implemented, 76 planned and 16 deferred; the example idea is excluded by stable ID. Full application CI is required on the new change; its exact run/commit evidence is recorded in the PR and Drive revision report, not assumed here. Spreadsheet import encountered an argument-binding error; the final destination was created natively and read back. This did not modify an existing user spreadsheet.

Security review: no new application entrypoint or external code execution, no dependency additions and no private keys in the roadmap. The validator uses only Python's standard library. Independent application/contract audits and comprehensive production scanning remain open roadmap gates, not completed by this documentation task.

Configuration, schema, logging and compatibility: no application changes. Existing defaults remain false-only for payments/restricted publication. CI permissions remain repository-read-only. The application's full configured lint/types/audit/migration/build/performance checks are rerun rather than replaced by document checks.

Rollback: revert the documentation commit using normal review. No database rollback is needed. Preserve the original b14dc4cf32e693808dcd326496db5336b7721399 application baseline and the live Sheet's user edits. GitHub and Sheets do not synchronize automatically. Read live changes before creating a later snapshot. No main merge, application tag, deployment or funds transfer is included.

Contributor note: the issue form appears in GitHub's chooser after merging into the default branch. Until then, add items to Ideas or copy docs/roadmap/ITEM_TEMPLATE.md into a blank issue.

Copyable commit notes are in ROADMAP.md. The exact published commit hash and full validation status are attached to the roadmap pull request.
