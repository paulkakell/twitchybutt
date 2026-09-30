"""Normalize real scanner reports without retaining code snippets or secret matches."""
from __future__ import annotations

import argparse
import datetime as dt
import hashlib
import json
from pathlib import Path

from security_gate import load


def composer_findings(report: dict) -> list[dict]:
    advisories = report.get("advisories")
    if advisories != [] and not isinstance(advisories, dict):
        raise ValueError("Invalid Composer advisory report")
    findings = []
    for entries in (advisories.values() if isinstance(advisories, dict) else []):
        if isinstance(entries, dict):
            entries = list(entries.values())
        if not isinstance(entries, list):
            raise ValueError("Invalid Composer advisory list")
        for advisory in entries:
            if not isinstance(advisory, dict):
                raise ValueError("Invalid Composer advisory")
            severity = advisory.get("severity", "unknown")
            findings.append({"severity": severity.lower() if isinstance(severity, str) else "unknown"})
    abandoned = report.get("abandoned")
    if abandoned != [] and not isinstance(abandoned, dict):
        raise ValueError("Missing abandoned-package assessment")
    if abandoned:
        # Requires review rather than silently claiming an unsupported package is safe.
        findings.append({"severity": "unknown"})
    return findings


def semgrep_findings(report: dict) -> list[dict]:
    if report.get("errors") != [] or not isinstance(report.get("results"), list):
        raise ValueError("Incomplete Semgrep scan")
    findings = []
    for result in report["results"]:
        if not isinstance(result, dict) or not isinstance(result.get("extra"), dict):
            raise ValueError("Invalid Semgrep result")
        severity = {"ERROR": "high", "WARNING": "medium", "INFO": "low"}.get(result["extra"].get("severity"), "unknown")
        findings.append({"severity": severity})
    return findings


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--commit", required=True)
    parser.add_argument("--version", required=True)
    parser.add_argument("--directory", type=Path, default=Path("build"))
    args = parser.parse_args()
    root = args.directory
    evidence = {"schema_version": 1, "commit": args.commit, "version": args.version,
                "generated_at": dt.datetime.now(dt.timezone.utc).isoformat(), "scans": {}}
    for name, filename in [("composer", "audit.json"), ("semgrep", "semgrep.json")]:
        scan = {"status": "failed", "commit": args.commit, "tool_version": None,
                "report_sha256": "", "scanned_count": 0, "findings": []}
        try:
            path = root / filename
            scan["report_sha256"] = hashlib.sha256(path.read_bytes()).hexdigest()
            report = load(path)
            if name == "composer":
                scan["findings"] = composer_findings(report)
                lock = load(Path("composer.lock"))
                scan["scanned_count"] = len(lock["packages"]) + len(lock["packages-dev"])
                scan["tool_version"] = (root / "composer-version.txt").read_text().strip()
            else:
                scan["findings"] = semgrep_findings(report)
                paths = report.get("paths", {}).get("scanned")
                if not isinstance(paths, list):
                    raise ValueError("Missing scanned paths")
                scan["scanned_count"] = len(paths)
                scan["tool_version"] = report.get("version")
            if scan["scanned_count"] > 0 and scan["tool_version"]:
                scan["status"] = "completed"
        except (OSError, ValueError, KeyError, TypeError, AttributeError):
            pass  # Remains failed; the gate blocks, including malformed/absent reports.
        evidence["scans"][name] = scan
    (root / "security-evidence.json").write_text(json.dumps(evidence, indent=2) + "\n")
    return 0 if all(s["status"] == "completed" for s in evidence["scans"].values()) else 1


if __name__ == "__main__":
    raise SystemExit(main())
