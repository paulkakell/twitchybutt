"""Regression fixtures, never release approval evidence."""
import copy
import datetime as dt
import importlib.util
import json
from pathlib import Path
import unittest

ROOT = Path(__file__).resolve().parents[2]
spec = importlib.util.spec_from_file_location("gate", ROOT / "scripts/security_gate.py")
gate = importlib.util.module_from_spec(spec)
spec.loader.exec_module(gate)
COMMIT = "a" * 40
NOW = dt.datetime(2026, 9, 27, tzinfo=dt.timezone.utc)


class GateTests(unittest.TestCase):
    def setUp(self):
        self.policy = {"schema_version": 1, "version": "00.04.00", "maximum_allowed_severity": "low",
                       "findings": [], "reviews": {name: {"status": "approved", "commit": COMMIT,
                       "reviewer": "unit-fixture", "evidence": "fixture-only"} for name in gate.REQUIRED_REVIEWS}}
        self.evidence = {"schema_version": 1, "version": "00.04.00", "commit": COMMIT,
                         "generated_at": NOW.isoformat(), "scans": {name: {"status": "completed",
                         "commit": COMMIT, "tool_version": "fixture", "report_sha256": "b" * 64,
                         "scanned_count": 1, "findings": []} for name in gate.REQUIRED_SCANS}}

    def check(self, release=True):
        return gate.evaluate(self.evidence, self.policy, COMMIT, "00.04.00", release=release, now=NOW)

    def test_complete_low_risk_fixture_passes(self):
        self.assertEqual([], self.check())

    def test_low_finding_is_permitted(self):
        self.evidence["scans"]["semgrep"]["findings"] = [{"severity": "low"}]
        self.assertEqual([], self.check())

    def test_every_above_low_and_unknown_scanner_severity_blocks(self):
        for severity in ["medium", "high", "critical", "unknown", "moderate", "", None]:
            with self.subTest(severity=severity):
                self.evidence["scans"]["semgrep"]["findings"] = [{"severity": severity}]
                self.assertTrue(self.check())

    def test_accepted_and_deferred_risks_are_not_fixes(self):
        for status in ["open", "in_progress", "accepted", "deferred"]:
            self.policy["findings"] = [{"id": "FIXTURE-1", "severity": "high", "status": status}]
            self.assertTrue(self.check())

    def test_closures_require_current_evidence_and_reviewer(self):
        for status in ["fixed", "false_positive"]:
            item = {"id": "FIXTURE-1", "severity": "high", "status": status}
            self.policy["findings"] = [item]
            self.assertTrue(self.check())
            item.update(reviewer="fixture", evidence="fixture", verified_commit=COMMIT)
            self.assertEqual([], self.check())
            item["verified_commit"] = "c" * 40
            self.assertTrue(self.check())

    def test_missing_or_failed_scans_block(self):
        for name in gate.REQUIRED_SCANS:
            scan = self.evidence["scans"].pop(name)
            self.assertTrue(self.check())
            self.evidence["scans"][name] = scan
            scan["status"] = "failed"
            self.assertTrue(self.check())
            scan["status"] = "completed"

    def test_empty_coverage_blocks(self):
        for value in [0, -1, None, True, "10"]:
            self.evidence["scans"]["semgrep"]["scanned_count"] = value
            self.assertTrue(self.check())

    def test_wrong_commit_or_version_blocks(self):
        for field in ["commit", "version"]:
            old = self.evidence[field]
            self.evidence[field] = "wrong"
            self.assertTrue(self.check())
            self.evidence[field] = old
        self.evidence["scans"]["composer"]["commit"] = "c" * 40
        self.assertTrue(self.check())

    def test_stale_future_naive_missing_timestamps_block(self):
        for stamp in [(NOW - dt.timedelta(hours=25)).isoformat(),
                      (NOW + dt.timedelta(minutes=6)).isoformat(), "2026-09-27", None]:
            self.evidence["generated_at"] = stamp
            self.assertTrue(self.check())

    def test_policy_threshold_cannot_be_relaxed(self):
        for value in ["medium", "critical", "none", None]:
            self.policy["maximum_allowed_severity"] = value
            self.assertTrue(self.check())

    def test_missing_reviews_block_release_not_development(self):
        self.policy["reviews"] = {}
        self.assertTrue(self.check())
        self.assertEqual([], self.check(release=False))

    def test_stale_review_blocks(self):
        self.policy["reviews"]["independent_security"]["commit"] = "c" * 40
        self.assertTrue(self.check())

    def test_duplicate_register_ids_block(self):
        item = {"id": "FIXTURE-1", "severity": "low", "status": "open"}
        self.policy["findings"] = [item, copy.deepcopy(item)]
        self.assertTrue(self.check())

    def test_malformed_scans_and_findings_block(self):
        for value in [None, "", 1, [], {"semgrep": []}]:
            self.evidence["scans"] = value
            self.assertTrue(self.check())

    def test_unknown_register_severity_blocks_even_closed(self):
        self.policy["findings"] = [{"id": "FIXTURE-1", "severity": "unknown", "status": "fixed",
                                    "reviewer": "fixture", "evidence": "fixture", "verified_commit": COMMIT}]
        self.assertTrue(self.check())

    def test_missing_digest_blocks(self):
        self.evidence["scans"]["semgrep"]["report_sha256"] = ""
        self.assertTrue(self.check())

    def test_duplicate_json_keys_rejected(self):
        with self.assertRaises(ValueError):
            json.loads('{"findings":[],"findings":[]}', object_pairs_hook=gate.unique_object)

    def test_real_policy_remains_blocked_for_release(self):
        self.policy = gate.load(ROOT / "security/release-policy.json")
        self.assertTrue(self.check())


if __name__ == "__main__":
    unittest.main()
