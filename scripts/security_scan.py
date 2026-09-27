"""Small, explicit guardrail scan. Not a replacement for independent SAST or review."""
from pathlib import Path
import re
import sys

ROOT = Path(__file__).resolve().parents[1]
errors = []
rules = {
    "dynamic execution": r"\b(?:eval|shell_exec|passthru|unserialize)\s*\(",
    "request mass assignment": r"(?:create|fill|update)\s*\(\s*\$request->(?:all|input)\s*\(",
    "unescaped template output": r"\{!!",
    "embedded private key": r"-----BEGIN (?:RSA |EC |OPENSSH )?PRIVATE KEY-----",
}
for folder in ("app", "routes", "resources", "config"):
    for path in (ROOT / folder).rglob("*"):
        if not path.is_file():
            continue
        text = path.read_text(encoding="utf-8")
        for label, pattern in rules.items():
            if re.search(pattern, text):
                errors.append(f"{path.relative_to(ROOT)}: {label}")
for line in (ROOT / ".env.example").read_text().splitlines():
    if line.startswith("APP_KEY=") and line != "APP_KEY=":
        errors.append("Example configuration contains an application key")
for flag in ("CMS_PAYMENTS_ENABLED=false", "CMS_RESTRICTED_PUBLISHING_ENABLED=false"):
    if flag not in (ROOT / ".env.example").read_text():
        errors.append("An unimplemented capability is not disabled by default")
if errors:
    print("\n".join(errors))
    sys.exit(1)
print("PASS: targeted execution, mass-assignment, template, secret and default-safety checks")
