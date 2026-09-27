"""Fail-closed release policy. No third-party Python packages are required."""
from __future__ import annotations

import argparse
import datetime as dt
import json
import re
from pathlib import Path
from typing import Any

SEVERITIES = {"info": 0, "low": 1, "medium": 2, "high": 3, "critical": 4}
REQUIRED_SCANS = {"composer", "semgrep"}
REQUIRED_REVIEWS = {
    "independent_security", "secret_history_scan", "deployment_image_scan", "m01_acceptance"
}
MAX_AGE_HOURS = 24


def unique_object(pairs: list[tuple[str, Any]]) -> dict[str, Any]:
    result: dict[str, Any] = {}
    for key, value in pairs:
        if key in result:
            raise ValueError("Duplicate JSON field")
        result[key] = value
    return result


def load(path: Path) -> dict[str, Any]:
    value = json.loads(path.read_text(encoding="utf-8"), object_pairs_hook=unique_object)
    if not isinstance(value, dict):
        raise ValueError("Expected JSON object")
    return value


def evaluate(evidence: dict[str, Any], policy: dict[str, Any], commit: str,
             version: str, *, release: bool = True,
             now: dt.datetime | None = None) -> list[str]:
    """Return every blocker. Unknown, accepted and deferred risks never mean fixed."""
    blockers: list[str] = []
    now = now or dt.datetime.now(dt.timezone.utc)
    if re.fullmatch(r"[0-9a-f]{40}", commit) is None:
        blockers.append("Invalid expected commit")
    if re.fullmatch(r"\d{2}\.\d{2}\.\d{2}", version) is None:
        blockers.append("Invalid expected version")
    if policy.get("schema_version") != 1 or policy.get("version") != version:
        blockers.append("Policy schema/version mismatch")
    if policy.get("maximum_allowed_severity") != "low":
        blockers.append("The maximum permitted severity must remain Low")
    if evidence.get("schema_version") != 1:
        blockers.append("Unsupported evidence schema")
    if evidence.get("commit") != commit or evidence.get("version") != version:
        blockers.append("Evidence is for a different commit/version")
    try:
        stamp = dt.datetime.fromisoformat(evidence["generated_at"])
        age = (now - stamp).total_seconds()
        if age < -300 or age > MAX_AGE_HOURS * 3600:
            blockers.append("Evidence is future-dated or older than 24 hours")
    except (KeyError, ValueError, TypeError):
        blockers.append("Missing or invalid evidence timestamp")
    scans = evidence.get("scans")
    if not isinstance(scans, dict):
        scans = {}
        blockers.append("Missing scanner evidence")
    for name in sorted(REQUIRED_SCANS):
        scan = scans.get(name)
        if not isinstance(scan, dict):
            blockers.append(f"{name}: missing scan")
            continue
        if scan.get("status") != "completed" or not scan.get("tool_version"):
            blockers.append(f"{name}: failed, incomplete or unidentified scan")
        if scan.get("commit") != commit:
            blockers.append(f"{name}: wrong commit")
        if re.fullmatch(r"[0-9a-f]{64}", str(scan.get("report_sha256", ""))) is None:
            blockers.append(f"{name}: missing report digest")
        count = scan.get("scanned_count")
        if type(count) is not int or count <= 0:
            blockers.append(f"{name}: empty or unknown scan coverage")
        findings = scan.get("findings")
        if not isinstance(findings, list):
            blockers.append(f"{name}: malformed findings")
            continue
        for index, finding in enumerate(findings):
            if not isinstance(finding, dict):
                blockers.append(f"{name}: malformed finding {index}")
                continue
            severity = finding.get("severity")
            if severity not in SEVERITIES or SEVERITIES[severity] > SEVERITIES["low"]:
                blockers.append(f"{name}: unresolved above-Low or unknown finding {index}")
    findings = policy.get("findings")
    if not isinstance(findings, list):
        blockers.append("Missing finding register")
        findings = []
    ids: set[str] = set()
    for index, finding in enumerate(findings):
        if not isinstance(finding, dict):
            blockers.append(f"Register: malformed finding {index}")
            continue
        identity = finding.get("id")
        if not isinstance(identity, str) or not identity or identity in ids:
            blockers.append(f"Register: missing or duplicate ID {index}")
        else:
            ids.add(identity)
        severity = finding.get("severity")
        status = finding.get("status")
        if severity not in SEVERITIES:
            blockers.append(f"Register: unknown severity {index}")
        if status not in {"open", "in_progress", "fixed", "false_positive", "accepted", "deferred"}:
            blockers.append(f"Register: unknown status {index}")
        if status in {"fixed", "false_positive"}:
            if not all(isinstance(finding.get(k), str) and finding[k].strip()
                       for k in ("reviewer", "evidence")) or finding.get("verified_commit") != commit:
                blockers.append(f"Register: unverified closure {index}")
        elif severity not in SEVERITIES or SEVERITIES[severity] > SEVERITIES["low"]:
            blockers.append(f"Register: above-Low finding {index} is not remediated")
    if release:
        reviews = policy.get("reviews", {})
        if not isinstance(reviews, dict):
            reviews = {}
        for name in sorted(REQUIRED_REVIEWS):
            review = reviews.get(name, {})
            if not isinstance(review, dict) or review.get("status") != "approved" or review.get("commit") != commit or not all(
                isinstance(review.get(k), str) and review[k].strip() for k in ("reviewer", "evidence")
            ):
                blockers.append(f"Release review required: {name}")
    return blockers


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--evidence", type=Path, required=True)
    parser.add_argument("--policy", type=Path, default=Path("security/release-policy.json"))
    parser.add_argument("--commit", required=True)
    parser.add_argument("--version", required=True)
    parser.add_argument("--development", action="store_true",
                        help="Check scan findings only; NEVER authorizes a release")
    args = parser.parse_args()
    try:
        blockers = evaluate(load(args.evidence), load(args.policy), args.commit,
                            args.version, release=not args.development)
    except (OSError, ValueError, TypeError, KeyError) as error:
        print(json.dumps({"release_allowed": False, "error_type": type(error).__name__}))
        return 1
    print(json.dumps({"release_allowed": not blockers and not args.development,
                      "mode": "development" if args.development else "release",
                      "blockers": blockers}, indent=2))
    return 1 if blockers else 0


if __name__ == "__main__":
    raise SystemExit(main())
