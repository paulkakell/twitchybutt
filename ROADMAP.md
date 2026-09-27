# TwitchyButt CMS product roadmap

Roadmap revision: **00.03.01**. Application baseline: **00.03.00**, an unmerged development preview. Tracking: [#3](https://github.com/paulkakell/twitchybutt/issues/3). This is an additive planning/documentation change, not a software feature release.

## Open the editable roadmap

[Open the live Google Sheets roadmap](https://docs.google.com/spreadsheets/d/1bdbGDfaQMY68vkWfJhkeO3uBuDx-qHyqr8Vjjp1av_A/edit).

The Sheet is in the user-designated TwitchyButtCMS Drive folder. Its tabs are **Start Here, Summary, Roadmap, Milestones, Decisions, Ideas, Release Checklist, Sources**. Roadmap and Ideas each have capacity for 1,000 entries. Sorting does not change stable IDs. The EXAMPLE idea is excluded from counts by ID, not by row position.

The live Sheet is the editable planning copy. GitHub remains authoritative for code, issues and delivered work. This repository contains a manually versioned snapshot, **not an automatic two-way synchronization**. Read and reconcile live Sheet edits by stable ID before starting subsequent implementation or publishing a replacement snapshot. Do not overwrite user additions with an older file.

### Add an item without editing code

1. Open **Ideas** and use the first empty row, initially row 3. The EXAMPLE row is illustrative only.
2. Enter an unused `IDEA-001` style identifier, title, user/problem and acceptance notes. Select **Idea** and an optional suggested priority/milestone.
3. At triage, give approved work the next unused permanent `TB-101` style ID in **Roadmap**. Add acceptance criteria, dependencies, owner, issue link and status. An idea does not automatically become scheduled scope.

Alternatively, use [the copyable item template](docs/roadmap/ITEM_TEMPLATE.md) in a GitHub issue. A structured [feature-request form](.github/ISSUE_TEMPLATE/feature-request.yml) is included; GitHub will expose it in the issue chooser only after it reaches the default branch. No project board or automation is implied.

## Product definition and boundaries

General-purpose, single-creator/business membership software with lawful adult-content support. Each creator operates their own domain, application, database, media storage, delivery and backups. No central media hosting, transcoding, previews or fan balances are part of the licensor service. Novice creators should ultimately deploy and maintain the supported configuration through guided workflows.

The business model has no upfront or fixed monthly software charge and a contractual **2% fee through supported crypto checkout**, separate from third-party costs. Fee basis, tax treatment, refund funding, token/network and commercial terms remain decisions. A self-hosted operator can modify their checkout; this roadmap does not promise technically unavoidable collection from unsupported forks.

Content neutrality is the product identity, not a switch that bypasses applicable safeguards. General sites should not receive indiscriminate adult-only presentation. Restricted capabilities require the appropriate reviewed workflows; labels alone are not eligibility or compliance evidence. This planning document is not legal, provider or production approval.

## Verified starting point

[PR #2](https://github.com/paulkakell/twitchybutt/pull/2), at commit `b14dc4cf32e693808dcd326496db5336b7721399`, contains accounts, local admin provisioning, classified text posts/studio, paid-body policies, TEST invoice quotes, reporting, migrations/configuration/logging and initial CI. The [baseline run](https://github.com/paulkakell/twitchybutt/actions/runs/36291212267) passed both database jobs; the inspected suite reported 56 tests. This evidence describes the baseline, not subsequent roadmap-change validation.

Eight foundation records are **Implemented**, not **Released**. There is no wallet settlement, actual 2% transfer, media pipeline, verified age/performer integration or turnkey installer in that baseline. The current snapshot has **100 items: 8 implemented, 76 planned, 16 deferred**, including 43 open P0 records. Counts are not percent-complete, effort estimates or production-readiness scores.

## Milestone sequence

Versions below are proposed sequencing targets, not dates or approved releases. Workstreams may proceed in parallel when their item-level dependencies permit. Milestone completion requires all applicable included work and its release controls, not just an illustrative happy path.

| Milestone | Proposed version | Items | Scope and exit condition |
| --- | --- | --- | --- |
| M00 | 00.03.00 | TB-001 to TB-008 | Review the executable application foundation and retain its tests; no production-readiness claim. |
| M01 | 00.04.00 | TB-009 to TB-020 | Email verification/recovery, admin MFA, sessions, authorization threat model, security scans, evidence retention, privacy/log redaction, health, accessibility and secure defaults. |
| M02 | 00.05.00 | TB-021 to TB-034 | Approve payment choices; signed invoices; atomic 98/2 contract; wallet UI; independent finality verification; exactly-once access; reconciliation; minimal licensing service; payout security; refunds/accounting; end-to-end testnet and independent contract review. |
| M03 | 00.06.00 | TB-035 to TB-044 | Creator-owned private storage, resumable uploads, quarantine, isolated transcoding, authorized media, accessible player, galleries, watermarks, deletion and budgets. |
| M04 | 00.07.00 | TB-045 to TB-054 | Prepaid memberships, manual renewals, paid posts/bundles, tips, member library, reconciled creator dashboard, branding, scheduling, notifications and coupons. |
| M05 | 00.08.00 | TB-055 to TB-064 | Reviewed markets/policies; classification; viewer assurance; creator/performer verification; consent/records; removal cases; duplicate response; notices; restricted-preview regressions and general/restricted site acceptance. |
| M06 | 00.09.00 | TB-065 to TB-074 | Supported hosting profiles, installation wizard, domain/HTTPS, signed upgrades, backups/restores, safe diagnostics, operator guides, private beta and measured load/cost tests. |
| M07 | 01.00.00 | TB-075 to TB-084 | Commercial and funds-flow approval, independent security review, controlled mainnet activation, monitoring, tested recovery, support economics, signed release artifacts, creator export and staged launch. |
| M08 | 01.01.00 | TB-085 to TB-094 | Optional growth: site-local SEO/search, comments, text messaging, separately authorized recurring debits, referrals, private analytics, localization, imports, team roles and themes. Select using pilot evidence. |
| M09 | 02.00.00 | TB-095 to TB-100 | Optional expansion: more crypto rails, mobile strategy, creator-hosted livestreams, fan attachments, signed extensions/API and opt-in metadata discovery without media hosting. Requires separate approval. |

[All 100 items, acceptance criteria and dependencies](docs/roadmap/items.tsv) are available as an editable TSV. [Milestone outcomes and prerequisites](docs/roadmap/milestones.tsv) are also versioned. The compact repository snapshot preserves seven planning columns; the live Sheet additionally carries owner, applicability, use case, effort, dates, issue/evidence links and notes. Those editable fields are not silently discarded from the live Sheet.

## Planning rules

**Priority:** P0 is required before the relevant launch; P1 is core planned scope; P2 is a later improvement; P3 is exploratory. P0 restricted-content work applies before that capability is enabled. A general-only pilot does not authorize a restricted or mainnet launch.

**Status:** Idea, Planned, Ready, In progress, Blocked, In review, Implemented, Released, Deferred or Dropped. Ready requires an owner, observable acceptance criteria and resolved prerequisites. Blocked requires a reason. Implemented requires reviewed implementation and relevant passing tests; Released additionally requires the approved exact tag/artifact and applicable release controls. Preserve deferred/dropped records instead of deleting their history.

**Sizing:** Effort remains TBD until assigned; S/M/L/XL are relative sizes, not day estimates. Target dates are blank until capacity, dependencies and external approvals are agreed. The roadmap does not authorize spending, assign work to named external reviewers or promise delivery dates.

**Dependencies:** use stable TB IDs separated by semicolons. Sorting and inserting rows must not renumber IDs. Tests reject missing dependencies, duplicates, self-dependencies and cycles. Update milestones.tsv when introducing a new milestone. Native Sheet validation and duplicate-ID warnings assist editing; the repository validator does not automatically run whenever someone edits the Sheet.

**Change control:** accept ideas in the Sheet, link an issue for execution, review scope, implement on a versioned branch, run all applicable checks and attach evidence before changing completion status. A request that changes hosting, custody, user-generated uploads or restricted-content controls needs a fresh risk/role review.

## Open decisions

The Decisions tab starts with 12 unresolved choices. Suggested participants are not assignments.

| ID | Decision | Related work |
| --- | --- | --- |
| DEC-01 | Choose the first token and network; no production asset is selected. | TB-021 |
| DEC-02 | Approve the exact hosting, storage, RPC, wallet, mail and conversion services for supported use. | TB-021, TB-065 |
| DEC-03 | Confirm crypto-first versus crypto-only positioning; cards are outside this roadmap, not permanently prohibited. | TB-021 |
| DEC-04 | Approve fee base, rounding, discounts, tax/tip treatment and examples. | TB-022 |
| DEC-05 | Decide refund funding, authorization and treatment of both shares. | TB-031 |
| DEC-06 | Approve launch markets and operator obligations after qualified role-specific review. | TB-055 |
| DEC-07 | Approve commercial licensing, source access, customization and termination rights. | TB-075 |
| DEC-08 | Approve minimal licensing-service metadata and contract/signing/control model. | TB-075, TB-076 |
| DEC-09 | Set support scope, budgets, pilot criteria and realistic capacity. | TB-073, TB-081 |
| DEC-10 | Approve treasury public address and key governance; no private keys belong here. | TB-030, TB-078 |
| DEC-11 | Set recovery-time and acceptable data-loss objectives, then prove them with restores. | TB-070, TB-080 |
| DEC-12 | Approve supported lawful restricted-content scope and verification/records providers. | TB-055, TB-057, TB-058 |

## Release controls

The Release Checklist tab is a reusable evidence template, initially **Not assessed** for future releases. A P0 or security blocker cannot be waived just by changing a status. No unresolved security finding above Low may remain in a released product.

1. Increment xx.xx.xx using the declared release/feature/fix strategy; align tags with the exact approved commit.
2. Update the change log with what, why, classification, issues and commits.
3. Run the full unit, integration and regression suites; add tests for changed behavior.
4. Run lint and static/type analysis; resolve structural/security warnings.
5. Review authentication, authorization, input, logging and secrets; remediate findings above Low.
6. Validate locked dependency compatibility, advisories, licenses and SBOM.
7. Build/install cleanly without hidden local dependencies.
8. Verify configuration, defaults, feature flags and examples.
9. Where schemas change, test forward/backward migration and practical data-preserving recovery.
10. Where core logic or I/O changes, run representative performance/load tests against agreed budgets.
11. Exercise structured/redacted logs and relevant metrics/alerts.
12. Update README, API/operator guides and architecture; include examples for every exposed option.
13. Review API, CLI, configuration and data-format compatibility; declare breaks explicitly.
14. Verify rollback, matching backups and accessible prior artifacts.
15. Attach release notes, checksums, provenance and signed artifacts where applicable.
16. Include copyable commit notes.

## Revision, validation and rollback

`docs/roadmap/VERSION` advances documentation from the 00.03.00 baseline to **00.03.01**. Root `VERSION` remains **00.03.00** because application behavior is unchanged. This documentation revision is traceable to its commit and PR; it is not an application release tag. Create a documentation tag only after review, without implying production approval.

Run `python3 -B docs/roadmap/validate.py` and `python3 -B -m unittest discover -s tests/roadmap -v`. The read-only CI pipeline also reruns the existing application tests, audit, lint/types, migrations, clean installations and HTTP/performance smoke checks. New validation results belong in the roadmap PR and Drive revision report; old test results must not be presented as new runs.

No runtime, dependency lock, database schema, secret, payment configuration or deployment is changed. Roll back the documentation commit through a reviewed revert; do not reset other branches or run database rollback. Preserve the live Sheet and later user edits. Before replacing a planning snapshot, compare against its latest revision and retain the prior snapshot.

Sources: the live baseline README/PR and supplied build report ground implemented status; all future scope is a planning proposal. GitHub's [template documentation](https://docs.github.com/en/communities/using-templates-to-encourage-useful-issues-and-pull-requests/configuring-issue-templates-for-your-repository) describes the default-branch activation requirement. Google's [table documentation](https://developers.google.com/workspace/sheets/api/guides/tables) describes native table/dropdown behavior. Do not add private identity records, wallet keys, customer data or sensitive legal/security details to this public repository.

## Copyable commit notes

```text
docs(00.03.01): add editable product roadmap and feature intake (#3)

Add 100 stable-ID items across ten proposed milestones.
Link the editable Drive roadmap, ideas inbox and open decisions.
Record acceptance criteria, dependencies and all release controls.
Add feature-request templates and 13 roadmap regression tests.
Rerun existing read-only application validation without runtime changes.
Keep root application VERSION at 00.03.00; no merge or deployment.
```
