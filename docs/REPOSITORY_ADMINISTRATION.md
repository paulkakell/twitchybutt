# Repository administration

Maintenance configuration dated 2026-09-27. Application VERSION and dependencies are unchanged; this is not a product release.

## Issue intake and community

`.github/ISSUE_TEMPLATE` contains bug, documentation and existing product-roadmap forms. Blank issue intake is disabled for the public chooser; questions go to Discussions and vulnerabilities to SECURITY.md. GitHub maintainers/API clients may still create issues without a form.

Discussions was already enabled when inspected. The Q&A (`q-a`) and Ideas (`ideas`) category forms are committed on main. The repository-setup workflow verifies category slugs and creates one welcome discussion in Announcements, falling back to General only if Announcements is absent. It preserves existing posts and labels. It does not pin posts or create/rename categories; any missing categories require an administrator. Its read-only inspection reports actual state, not assumed activation.

The setup job is limited to this repository's main branch. Only that job receives issues/discussions write permissions. Pull-request validation has contents read permission only, no secrets and no `pull_request_target` execution. Workflow concurrency prevents duplicate setup runs. The helper paginates discussions and checks its stable marker/author before creating a welcome post; reruns do not overwrite community contributions.

## Sponsor button

`.github/FUNDING.yml` points only to `paulkakell` on GitHub Sponsors. No alternative beneficiary, wallet or external payment service has been invented. The owner must have a public Sponsors listing and enable Sponsorships under Settings > General. The setup report checks the listing and repository funding links; committing this file alone does not prove the button is visible or the account can receive funds.

Enrollment, tax/banking verification and payout setup remain owner-controlled. Sponsorship is voluntary, separate from the proposed 2% software fee, and does not change the proprietary license, purchase access or clear release gates.

## Dependabot version-update and grouping rules

`.github/dependabot.yml` monitors the two ecosystems currently on main:

| Ecosystem | Routine version checks (America/Denver) | Maximum version PRs |
| --- | --- | --- |
| Composer, root manifest/lock including transitive dependencies | Monday 06:00 | 10 |
| GitHub Actions, workflow action references | Monday 06:30 | 5 |

Routine new versions have a seven-day cooldown. Composer production and development minor/patch updates are grouped separately; Actions minor/patch updates are grouped. Major version changes remain individual PRs, not ignored. Owner assignment and commit prefixes are configured. Dependabot's default dependency/ecosystem labels are retained.

Each ecosystem has a separate security-update group. Security updates are not delayed by these routine version schedules/cooldowns and have a separate GitHub PR limit. No `target-branch` redirects security handling away from the default branch. No ignore list, private-registry secret, external-code execution opt-in or automatic merge is added. PHP extensions, the operating system, native executables and arbitrary image strings in CI are not certified or comprehensively maintained by this file.

## Administrator-only security settings: verify separately

The connected GitHub app can write files/issues/PRs/workflows but does not expose administration or Dependabot-alert management. The workflow's GITHUB_TOKEN is not an administrator credential. Do not store a personal access token merely to bypass these boundaries.

In Settings > Advanced Security, the owner should verify the dependency graph, Dependabot alerts, Dependabot security updates and private vulnerability reporting are enabled. Under Dependabot rules, ensure no preset/custom rule automatically dismisses or snoozes alerts; even a development dependency can block release. These UI states cannot be claimed from dependabot.yml.

The chosen security behavior is **security updates enabled for every fixable alert, no suppression**. GitHub's custom "open a pull request" auto-triage action requires security updates to be disabled; do not disable broad security coverage just to add a redundant rule. A custom auto-triage rule in Settings is not created by this maintenance commit. Any unverified admin settings remain an explicit follow-up, not completed work.

Required reviews/branch protection and external release approvals are separate controls. Keep the existing above-Low release policy; never make a blocked candidate green by skipping the release-readiness job. No release tag, deployment, real transfer or application feature enablement is part of this setup.

## Rollback and verification

Run configuration validation locally and inspect both existing application database jobs on the maintenance PR. After merge, inspect repository-setup logs for actual category/funding/welcome results and the initial Dependabot run. If a setup write fails, fix the stated permission/category problem and rerun the workflow; do not infer success. Configuration validation is not an independent security review.

Rollback by reverting the maintenance commit/PR; this disables/removes the configured files but does not delete already-created discussions, labels or Dependabot PRs. Review those separately and preserve user comments. No database migration is involved. The live roadmap Sheet is not modified or synchronized by this work.

## References

- [Dependabot options](https://docs.github.com/en/code-security/reference/supply-chain-security/dependabot-options-reference)
- [Native auto-triage rules and security-update interaction](https://docs.github.com/en/code-security/how-tos/secure-your-supply-chain/manage-your-dependency-security/auto-triage-dependabot-alerts)
- [Sponsor button configuration](https://docs.github.com/en/repositories/managing-your-repositorys-settings-and-features/customizing-your-repository/displaying-a-sponsor-button-in-your-repository)
- [Discussion category forms](https://docs.github.com/en/discussions/managing-discussions-for-your-community/creating-discussion-category-forms)
