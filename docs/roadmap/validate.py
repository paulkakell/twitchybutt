"""Validate the reviewable roadmap snapshot without external dependencies."""
from __future__ import annotations

import csv
import re
import sys
from pathlib import Path

FIELDS = ("ID", "Item", "Milestone", "Priority", "Status", "Dependencies", "Acceptance criteria")
STATUSES = {"Idea", "Planned", "Ready", "In progress", "Blocked", "In review", "Implemented", "Released", "Deferred", "Dropped"}


def validate(rows: list[dict[str, str]], milestones: set[str]) -> None:
    if not rows:
        raise ValueError("Roadmap must contain at least one item")
    graph: dict[str, list[str]] = {}
    for row in rows:
        if set(row) != set(FIELDS) or any(not isinstance(v, str) for v in row.values()):
            raise ValueError("Invalid columns or malformed TSV row")
        identifier = row["ID"]
        if not re.fullmatch(r"TB-[0-9]{3,}", identifier):
            raise ValueError(f"Invalid ID: {identifier}")
        if identifier in graph:
            raise ValueError(f"Duplicate ID: {identifier}")
        if not row["Item"].strip() or not row["Acceptance criteria"].strip():
            raise ValueError(f"Missing title or acceptance criteria: {identifier}")
        if row["Milestone"] not in milestones:
            raise ValueError(f"Unknown milestone: {identifier}")
        if row["Priority"] not in {"P0", "P1", "P2", "P3"} or row["Status"] not in STATUSES:
            raise ValueError(f"Invalid priority or status: {identifier}")
        dependencies = [part.strip() for part in row["Dependencies"].split(";") if part.strip()]
        if len(dependencies) != len(set(dependencies)):
            raise ValueError(f"Duplicate dependency: {identifier}")
        graph[identifier] = dependencies
    for identifier, dependencies in graph.items():
        if identifier in dependencies:
            raise ValueError(f"Self dependency: {identifier}")
        if any(dependency not in graph for dependency in dependencies):
            raise ValueError(f"Unknown dependency: {identifier}")
    # Kahn's algorithm also supports large user-extended roadmaps without recursion limits.
    pending = {identifier: len(dependencies) for identifier, dependencies in graph.items()}
    dependents: dict[str, list[str]] = {identifier: [] for identifier in graph}
    for identifier, dependencies in graph.items():
        for dependency in dependencies:
            dependents[dependency].append(identifier)
    ready = [identifier for identifier, count in pending.items() if count == 0]
    visited = 0
    while ready:
        identifier = ready.pop()
        visited += 1
        for dependent in dependents[identifier]:
            pending[dependent] -= 1
            if pending[dependent] == 0:
                ready.append(dependent)
    if visited != len(graph):
        raise ValueError("Dependency cycle detected")


def check_snapshot(root: Path) -> int:
    version = (root / "VERSION").read_text(encoding="utf-8").strip()
    if not re.fullmatch(r"[0-9]{2}\.[0-9]{2}\.[0-9]{2}", version):
        raise ValueError("Roadmap revision must use xx.xx.xx")
    with (root / "milestones.tsv").open(encoding="utf-8", newline="") as handle:
        records = list(csv.DictReader(handle, delimiter="\t"))
    milestones = {record["Milestone"] for record in records}
    if len(records) != len(milestones) or not milestones:
        raise ValueError("Missing or duplicate milestones")
    for record in records:
        if not re.fullmatch(r"M[0-9]{2,}", record["Milestone"]):
            raise ValueError("Invalid milestone ID")
    with (root / "items.tsv").open(encoding="utf-8", newline="") as handle:
        rows = list(csv.DictReader(handle, delimiter="\t"))
    validate(rows, milestones)
    return len(rows)


if __name__ == "__main__":
    try:
        count = check_snapshot(Path(__file__).resolve().parent)
    except (OSError, KeyError, ValueError, csv.Error) as exc:
        print(f"Roadmap validation failed: {exc}", file=sys.stderr)
        raise SystemExit(1) from exc
    print(f"PASS: {count} unique roadmap items; valid fields and acyclic dependencies")
