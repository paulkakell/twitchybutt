# 00.04.00 iteration validation history

Reference #5. Preserve failed runs and distinguish development validation from release approval. Application changes remain on build/00.04.00; no merge, release tag or deployment is authorized here.

| Commit | Actions run | Observed result and action |
| --- | --- | --- |
| 346ccf67d2c95395c13981825fddd2c448c119a1 | 36293888537 | New logging tests used an unsupported purge method; the operator-route regression also failed. Corrected the harness without weakening assertions. |
| 8db9f661b8ab7a5074127cd1f7e2b7a598e704c8 | 36294077459 | Logging tests passed; remaining route regression exposed null returned by the bool administrator gate for an unrefreshed model. Require explicit true; deny missing/null roles. |
| 3b040ac0191e7a4774ee85fb9f716c0dc0cd167d | 36294324685 | Both SQLite/PostgreSQL validation jobs and security job passed. Full release-readiness correctly failed for deployment-image, independent-security, M01-acceptance and secret-history reviews. The artifact download step warned about an older Node runtime; replaced below. |

## CI dependency correction

Replace actions/download-artifact commit 018cc2cf5baa6db3ef3c5f8a56943fffe632ef53 with the verified v8.0.1 commit 3e5f45b2cfb9172054b4087a40e8e0b5a5461e7c. Its action.yml explicitly targets Node24 and supports digest-mismatch:error; that error behavior is set explicitly. The action reads only the named artifact from the same workflow run. No token-scope elevation or repository write permission is added.

Primary references inspected:
https://github.com/actions/download-artifact/releases/tag/v8.0.1
https://github.com/actions/download-artifact/blob/3e5f45b2cfb9172054b4087a40e8e0b5a5461e7c/action.yml

Re-run the complete pipeline after this correction. The final exact commit, run, test counts and artifact references are recorded in the iteration pull request and Drive report once execution completes. This document does not predict that future result.

## Release remains blocked

A passing development-mode scanner check never grants release approval. Pending independent assessment, secret-history/image evidence and remaining M01 requirements are not waived. All unresolved findings above Low, and any unknown severity, block release. No finding was reclassified to make this iteration pass. Six custom SAST rules are limited coverage, not comprehensive security assurance.
