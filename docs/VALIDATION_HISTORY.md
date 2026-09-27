# Validation history: 00.03.00

The milestone is unreleased. Checks are tied to exact commits, not assumed from source files.

## Run 36290711125

https://github.com/paulkakell/twitchybutt/actions/runs/36290711125
Implementation: 88cbd860cdba41916c91b6fa45a057f162668687.
Generated lock/format revision tested: 79a8ad97af85cd77237b16d5d8e7d0bf9748e64c.

Fresh SQLite/PostgreSQL installs, strict Composer validation, platform checks, dependency audit, syntax, Pint, PHPStan/Larastan, targeted source checks, forward/rollback migrations and configuration diagnostics passed. The feature harness failed because Mockery was missing. Twenty-two amount-test cases passed; all 27 feature tests errored before their assertions. Later build/HTTP/install steps were skipped, not passed.

## Run 36290961175

https://github.com/paulkakell/twitchybutt/actions/runs/36290961175
Correction: a469015379db0053bcb79e2f46a1d7096b1449f7.
Generated corrected lock tested: 989170c543fac00c3dc27df660184699cc84c669.

Mockery 1.6.15 and Hamcrest 3.0.0 were added without changing the other locked packages. The inspected SQLite log shows 55 tests, 20,171 assertions and one failure: report rate-limiting inherited state from another test. The other 54 tests passed, including new malformed-input, password-byte, local-administrator and production-cache checks. Subsequent checks remained skipped in that run.

## Follow-up

Set PHPUnit's server-variable overrides explicitly so framework configuration uses isolated array cache/session stores rather than inherited CI values. Add an assertion for that isolation. Do not relax the application rate limit to hide a test failure. Remove the now-unneeded write-enabled lock bootstrap; all subsequent validation is read-only and uses the committed lock.

These are construction fixes in unreleased 00.03.00. Reference #1. The final PR and Drive build report identify the final tested commit, run, results and remaining gaps. This history does not assert that a later run passed.
