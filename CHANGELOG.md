# Change log

## 00.07.01 - Media validation repair (unreleased)

Classification: fix. Reference #9. Baseline 00.07.00 at 21b6757e022b2cfc78ddb997410e82ad5a6e78da. Explicitly install GD/FFmpeg on disposable CI runners and record package/tool versions; the earlier runner omitted required tools and skipped application execution. Keep all prerequisite assertions instead of skipping media tests.

Correct the subprocess-environment test to create and restore its own synthetic library-path fixture instead of assuming a portable local runtime. Add actual child-process environment checks with present and absent library overrides, including getenv, server and environment secret sources. No secret values or subprocess environment output are retained as artifacts.

No application dependency, schema, fee or feature-default change. Existing private-media, MFA and access boundaries remain enforced. Update current documentation and record failed runs by commit. Full SQLite/PostgreSQL, SMTP/HTTP/MFA/media/concurrency, fresh build, style/type and security checks are required on the final candidate. Above-Low findings and incomplete security reviews still prohibit release.


## Earlier increments

[Complete change-log history through 00.07.00](docs/changelog/through-00.07.00.md) is preserved without editing earlier entries. No prior release or security approval is implied.
