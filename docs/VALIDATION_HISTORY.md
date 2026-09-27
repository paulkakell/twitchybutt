# Validation history: 00.03.00

The milestone is unreleased. Tests are recorded against exact commits, not assumed from source files.

## First implementation run

Run: https://github.com/paulkakell/twitchybutt/actions/runs/36290711125
Implementation commit: 88cbd860cdba41916c91b6fa45a057f162668687.
Generated dependency-lock/format commit actually tested: 79a8ad97af85cd77237b16d5d8e7d0bf9748e64c.

SQLite and PostgreSQL fresh installs, strict Composer validation, platform checks, dependency audit, PHP syntax, Pint, PHPStan/Larastan, targeted security checks, forward/rollback migrations and configuration diagnostics passed. No package vulnerability advisory was returned at the time of this run.

The full PHPUnit attempt found a missing development dependency: Mockery was required by Laravel's RefreshDatabase console harness. The 22 amount-test cases passed; all 27 feature tests errored before their assertions. The run failed. Subsequent HTTP, cached-build and production-only installation checks were skipped, not passed.

## Corrections under validation

Add mockery/mockery explicitly and update only that package and its required dependencies in the lock. Harden array-valued email input, bcrypt's 72-byte boundary and null-byte validation; test local administrator provisioning and a production cache guard. Require 64-bit PHP before serving requests. Upgrade the pinned checkout action to its verified Node24 revision and pin the observed PostgreSQL image digest; use the intended DB user in its health check.

These are fixes during construction of unreleased 00.03.00, not a new deployed API. References: #1. The final Drive build report and PR will contain the latest exact tested commit and remaining failures or completed results. Do not infer success from this file.
