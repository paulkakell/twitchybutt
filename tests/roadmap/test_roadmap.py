"""Regression checks for editable roadmap records; no application state is touched."""
from __future__ import annotations

import copy
import importlib.util
import tempfile
import unittest
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2] / "docs" / "roadmap"
SPEC = importlib.util.spec_from_file_location("roadmap_validator", ROOT / "validate.py")
assert SPEC is not None and SPEC.loader is not None
MODULE = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(MODULE)


class RoadmapValidationTest(unittest.TestCase):
    def setUp(self) -> None:
        self.rows = [{"ID": "TB-001", "Item": "Example item", "Milestone": "M00", "Priority": "P1", "Status": "Planned", "Dependencies": "", "Acceptance criteria": "An observable test passes."}]

    def test_repository_snapshot(self) -> None:
        self.assertGreater(MODULE.check_snapshot(ROOT), 0)

    def test_valid_item(self) -> None:
        MODULE.validate(self.rows, {"M00"})

    def test_blank_roadmap(self) -> None:
        with self.assertRaisesRegex(ValueError, "at least one"):
            MODULE.validate([], {"M00"})

    def test_duplicate_ids(self) -> None:
        with self.assertRaisesRegex(ValueError, "Duplicate ID"):
            MODULE.validate(self.rows * 2, {"M00"})

    def test_unknown_dependency(self) -> None:
        self.rows[0]["Dependencies"] = "TB-999"
        with self.assertRaisesRegex(ValueError, "Unknown dependency"):
            MODULE.validate(self.rows, {"M00"})

    def test_self_dependency(self) -> None:
        self.rows[0]["Dependencies"] = "TB-001"
        with self.assertRaisesRegex(ValueError, "Self dependency"):
            MODULE.validate(self.rows, {"M00"})

    def test_duplicate_dependencies(self) -> None:
        self.rows[0]["Dependencies"] = "TB-002; TB-002"
        with self.assertRaisesRegex(ValueError, "Duplicate dependency"):
            MODULE.validate(self.rows, {"M00"})

    def test_cycle(self) -> None:
        second = dict(self.rows[0], ID="TB-002", Dependencies="TB-001")
        self.rows[0]["Dependencies"] = "TB-002"
        with self.assertRaisesRegex(ValueError, "cycle"):
            MODULE.validate(self.rows + [second], {"M00"})

    def test_forward_reference_and_sorted_rows(self) -> None:
        second = dict(self.rows[0], ID="TB-002", Dependencies="TB-001")
        MODULE.validate([second] + self.rows, {"M00"})

    def test_new_items_are_not_limited_to_initial_count(self) -> None:
        additions = [dict(self.rows[0], ID=f"TB-{n:03d}", Dependencies="TB-001") for n in range(2, 1201)]
        MODULE.validate(self.rows + additions, {"M00"})

    def test_invalid_fields(self) -> None:
        cases = [("ID", "DEC-01"), ("Item", ""), ("Acceptance criteria", " "), ("Milestone", "M99"), ("Priority", "Urgent"), ("Status", "Complete")]
        for field, value in cases:
            with self.subTest(field=field):
                rows = copy.deepcopy(self.rows)
                rows[0][field] = value
                with self.assertRaises(ValueError):
                    MODULE.validate(rows, {"M00"})

    def test_malformed_tsv_row(self) -> None:
        self.rows[0]["Extra"] = "bad"
        with self.assertRaisesRegex(ValueError, "Invalid columns"):
            MODULE.validate(self.rows, {"M00"})

    def test_invalid_version(self) -> None:
        with tempfile.TemporaryDirectory() as folder:
            root = Path(folder)
            (root / "VERSION").write_text("latest", encoding="utf-8")
            with self.assertRaisesRegex(ValueError, "xx.xx.xx"):
                MODULE.check_snapshot(root)


if __name__ == "__main__":
    unittest.main()
