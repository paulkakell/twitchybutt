"""Exercise the real Ruby validator against isolated repository configuration.

JSON fixtures are also YAML, so the tests need only Python's standard library
and the same Ruby installation as the production validation command.
"""
import json
from pathlib import Path
import shutil
import subprocess
import tempfile
import unittest

ROOT = Path(__file__).resolve().parents[2]
POLICY = "https://github.com/paulkakell/twitchybutt/security/policy"
SUPPORT = "https://github.com/paulkakell/twitchybutt/discussions"


class ContactLinkValidationTests(unittest.TestCase):
    def setUp(self):
        self.directory = tempfile.TemporaryDirectory()
        self.addCleanup(self.directory.cleanup)
        self.root = Path(self.directory.name)
        shutil.copytree(ROOT / ".github", self.root / ".github")
        (self.root / "scripts").mkdir()
        shutil.copy2(ROOT / "scripts/validate_repository.rb", self.root / "scripts/validate_repository.rb")
        self.chooser = self.root / ".github/ISSUE_TEMPLATE/config.yml"

    def validate(self):
        return subprocess.run(
            ["ruby", "scripts/validate_repository.rb"], cwd=self.root,
            text=True, capture_output=True, timeout=15, check=False,
        )

    def set_contacts(self, contacts):
        self.chooser.write_text(json.dumps({
            "blank_issues_enabled": False, "contact_links": contacts,
        }), encoding="utf-8")

    def set_urls(self, urls):
        self.set_contacts([{"name": "Support", "about": "Contact the owner", "url": url} for url in urls])

    def assert_rejected(self, reason):
        result = self.validate()
        self.assertEqual(1, result.returncode, result.stdout + result.stderr)
        self.assertIn(reason, result.stderr)

    def test_checked_in_configuration_passes(self):
        result = self.validate()
        self.assertEqual(0, result.returncode, result.stdout + result.stderr)

    def test_exact_urls_are_accepted_in_either_order(self):
        for urls in ([POLICY, SUPPORT], [SUPPORT, POLICY]):
            with self.subTest(urls=urls):
                self.set_urls(urls)
                result = self.validate()
                self.assertEqual(0, result.returncode, result.stdout + result.stderr)

    def test_embedded_urls_and_lookalike_destinations_are_rejected(self):
        for expected, other, reason in (
            (POLICY, SUPPORT, "Private-reporting policy linked"),
            (SUPPORT, POLICY, "Community support linked"),
        ):
            variants = [
                "https://attacker.invalid/" + expected,
                "https://attacker.invalid/?next=" + expected,
                "https://attacker.invalid/#" + expected,
                expected.replace("github.com", "github.com.attacker.invalid"),
                expected.replace("github.com", "github.com@attacker.invalid"),
                expected.replace("github.com", "attacker.invalid@github.com"),
                expected.replace("https://", "http://"),
                expected.replace("https:", ""),
                expected.replace("github.com", "github.com:444"),
                expected + ".attacker.invalid",
                expected + "?next=https://attacker.invalid",
                expected + "#https://attacker.invalid",
                expected + "/../other",
                " " + expected,
                expected + "\n",
                expected.replace("paulkakell", "another-owner"),
                expected.replace("twitchybutt", "another-repository"),
            ]
            for candidate in variants:
                with self.subTest(expected=expected, candidate=candidate):
                    self.set_urls([candidate, other])
                    self.assert_rejected(reason)

    def test_extra_untrusted_link_is_rejected_even_when_required_links_exist(self):
        self.set_urls([POLICY, SUPPORT, "https://attacker.invalid"])
        self.assert_rejected("Only canonical owner contact destinations")

    def test_duplicate_destination_is_rejected(self):
        self.set_urls([POLICY, SUPPORT, POLICY])
        self.assert_rejected("Contact destinations must not be duplicated")

    def test_missing_required_destination_is_rejected(self):
        for urls, reason in (([POLICY], "Community support linked"), ([SUPPORT], "Private-reporting policy linked")):
            with self.subTest(urls=urls):
                self.set_urls(urls)
                self.assert_rejected(reason)

    def test_contact_links_must_be_a_nonempty_array(self):
        for contacts in (None, {}, POLICY, []):
            with self.subTest(contacts=contacts):
                self.set_contacts(contacts)
                self.assert_rejected("Contact links must be a nonempty array")

    def test_contact_urls_must_be_strings_in_mappings(self):
        for contacts in ([POLICY], [None], [{}], [{"url": None}], [{"url": [POLICY]}], [{"url": 1}]):
            with self.subTest(contacts=contacts):
                self.set_contacts(contacts)
                self.assert_rejected("Contact URLs must be strings")

    def test_duplicate_yaml_keys_still_fail_closed(self):
        self.chooser.write_text("blank_issues_enabled: false\nblank_issues_enabled: true\n", encoding="utf-8")
        self.assert_rejected("duplicate YAML keys")

    def test_unrelated_workflow_cannot_acquire_write_permissions(self):
        workflow = {
            "on": {"push": None}, "permissions": {"contents": "read"},
            "jobs": {"untrusted": {"permissions": {"contents": "write"}, "steps": []}},
        }
        (self.root / ".github/workflows/untrusted.yml").write_text(json.dumps(workflow), encoding="utf-8")
        self.assert_rejected("scoped community writes only")


if __name__ == "__main__":
    unittest.main()
