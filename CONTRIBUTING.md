# Contributing

TwitchyButt CMS is content-neutral, self-hosted software in development. Public repository visibility is not an open-source/commercial license grant. This maintenance change does not alter licensing terms.

## Choose the right place

Use Discussions Q&A for usage questions and Ideas for exploratory proposals. Use the bug and documentation issue forms for actionable problems. The existing Product roadmap item form remains the intake for scoped features; include stable TB IDs where known. A request is not a delivery promise. Keep all examples synthetic and follow [SECURITY.md](SECURITY.md) for suspected vulnerabilities.

## Submit a change

Branch from the appropriate current base. Repository/community configuration targets `main`; application work toward 01.00.00 is consolidated in PR #10 on `build/01.00.00` until its gates are satisfied. Do not resurrect obsolete version branches.

Describe the problem, link the relevant issue, explain compatibility/migrations/rollback and record the exact tested commit. For application changes run the documented SQLite and PostgreSQL checks, Composer audit, lint/type checks and security/release-policy tests applicable to that branch. Do not run application tests against production data.

For repository configuration, run:

```sh
ruby scripts/validate_repository.rb
python3 -B -m unittest discover -s tests/repository -v
```

The configuration validator uses Ruby's standard YAML parser; the setup helper/tests use Python's standard library. No new application package is needed. Validation does not substitute for checking that GitHub activated an administrator-only setting.

## Review and triage

Maintain a respectful, evidence-based discussion. Reproducible bugs receive an owner and acceptance criteria; duplicates link to the canonical tracker. Close work as completed only with implementation/merge evidence. Superseded work must state where the remaining requirements are tracked, rather than pretending they passed.

Dependabot updates are reviewed like other code. Routine minor/patch updates are grouped; major changes remain separate. No automatic merges or alert suppression are authorized. Every unresolved finding above Low and incomplete required evidence continues to block release. Neither merging documentation nor posting an announcement approves production deployment, real payments or restricted publication.
