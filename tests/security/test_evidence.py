import sys
from pathlib import Path
import unittest

sys.path.insert(0, str(Path(__file__).resolve().parents[2] / "scripts"))
from security_evidence import composer_findings, semgrep_findings


class EvidenceTests(unittest.TestCase):
    def test_empty_valid_reports(self):
        self.assertEqual([], composer_findings({"advisories": [], "abandoned": []}))
        self.assertEqual([], semgrep_findings({"errors": [], "results": []}))

    def test_composer_severity_preserved(self):
        report = {"advisories": {"package": [{"severity": "critical"}]}, "abandoned": {}}
        self.assertEqual([{"severity": "critical"}], composer_findings(report))

    def test_missing_severity_stays_unknown(self):
        self.assertEqual([{"severity": "unknown"}], composer_findings({"advisories": {"package": [{}]}, "abandoned": []}))

    def test_abandoned_dependency_requires_review(self):
        self.assertEqual([{"severity": "unknown"}], composer_findings({"advisories": [], "abandoned": {"package": None}}))

    def test_missing_composer_fields_fail(self):
        for report in [{}, {"advisories": []}, {"advisories": None, "abandoned": []}]:
            with self.assertRaises(ValueError):
                composer_findings(report)

    def test_semgrep_warning_is_not_treated_as_low(self):
        self.assertEqual([{"severity": "medium"}], semgrep_findings({"errors": [], "results": [{"extra": {"severity": "WARNING"}}]}))

    def test_semgrep_errors_fail_even_without_findings(self):
        for report in [{}, {"errors": ["scan error"], "results": []}, {"errors": [], "results": None}]:
            with self.assertRaises(ValueError):
                semgrep_findings(report)

    def test_no_sensitive_snippets_in_normalized_findings(self):
        actual = semgrep_findings({"errors": [], "results": [{"extra": {"severity": "ERROR", "lines": "PRIVATE_SENTINEL"}}]})
        self.assertEqual([{"severity": "high"}], actual)
        self.assertNotIn("PRIVATE_SENTINEL", str(actual))


if __name__ == "__main__":
    unittest.main()
