"""Tests for pz_parser phase 3 — mod attribution."""
from __future__ import annotations

import pathlib
import sys
import unittest

sys.path.insert(0, str(pathlib.Path(__file__).resolve().parents[1]))

import pz_parser  # noqa: E402

FIXTURE_DIR = pathlib.Path(__file__).resolve().parent / "fixtures"


def fixture(name: str) -> pathlib.Path:
    return FIXTURE_DIR / name


class AttributionBucketTests(unittest.TestCase):
    """Three confidence buckets: direct (high), inferred (medium),
    unattributed (low)."""

    def test_direct_attribution_when_lua_marker_on_entry(self) -> None:
        entries = pz_parser.parse_file(fixture("fixture_lua_attributed.txt"))
        records = pz_parser.classify_entries(entries, source_file="la.txt")
        self.assertEqual(len(records), 1)
        rec = records[0]
        self.assertEqual(rec.attribution, "direct")
        self.assertEqual(rec.confidence, "high")
        # mod_id is normalised: lowercase, no spaces / apostrophes / hyphens.
        self.assertEqual(rec.mod_id, "testmodalpha")
        self.assertEqual(rec.mod_name, "Test Mod Alpha")

    def test_inferred_attribution_within_lookback_window(self) -> None:
        entries = pz_parser.parse_file(fixture("fixture_inferred.txt"))
        records = pz_parser.classify_entries(entries, source_file="in.txt")
        self.assertEqual(len(records), 1)
        rec = records[0]
        self.assertEqual(rec.attribution, "inferred")
        self.assertEqual(rec.confidence, "medium")
        self.assertEqual(rec.mod_id, "spongiesclothing")

    def test_unattributed_when_no_marker_and_not_lua_shaped(self) -> None:
        entries = pz_parser.parse_file(fixture("fixture_unattributed.txt"))
        records = pz_parser.classify_entries(entries, source_file="ua.txt")
        self.assertEqual(len(records), 1)
        rec = records[0]
        self.assertEqual(rec.attribution, "unattributed")
        self.assertEqual(rec.confidence, "low")
        self.assertEqual(rec.mod_id, "__unattributed__")


class LookbackBoundaryTests(unittest.TestCase):
    """Phase 3 — 40-line inferred-attribution window boundary."""

    def test_lua_marker_beyond_lookback_does_not_attribute(self) -> None:
        # Fixture places the Lua((MOD:...)) >40 lines before the ERROR.
        entries = pz_parser.parse_file(fixture("fixture_lookback_boundary.txt"))
        records = pz_parser.classify_entries(entries, source_file="lb.txt")
        self.assertEqual(len(records), 1)
        rec = records[0]
        # The Lua-shaped ERROR is far enough back to be unattributed.
        self.assertEqual(rec.attribution, "unattributed")
        self.assertEqual(rec.mod_id, "__unattributed__")

    def test_non_lua_shaped_body_rejects_inferred_attribution(self) -> None:
        # Recent Lua((MOD:Spongies Clothing)) emitted, but the ERROR body
        # ("Disk full while writing chunk data") isn't Lua-shaped.
        entries = pz_parser.parse_file(fixture("fixture_non_lua_no_inferred.txt"))
        records = pz_parser.classify_entries(entries, source_file="nl.txt")
        self.assertEqual(len(records), 1)
        rec = records[0]
        self.assertEqual(rec.attribution, "unattributed")


class NeededByTests(unittest.TestCase):
    """Phase 3 — direct attribution via "needed by <mod>" hint."""

    def test_needed_by_extracts_dependent_mod(self) -> None:
        entries = pz_parser.parse_file(fixture("fixture_require_failed.txt"))
        records = pz_parser.classify_entries(entries, source_file="rf.txt")
        self.assertEqual(len(records), 1)
        rec = records[0]
        # "needed by Test Mod Alpha" should set the mod to Test Mod Alpha
        # (preferred over the require("...") side which would mention
        # DependencyMod). Either way we want direct/high.
        self.assertEqual(rec.attribution, "direct")
        self.assertEqual(rec.confidence, "high")
        # The "needed by" branch is checked before the require() branch in
        # the priority order; mod_id should reflect Test Mod Alpha.
        self.assertEqual(rec.mod_id, "testmodalpha")


def _make_marker_line(idx: int) -> str:
    """Synthesise a single LOG-level entry containing a Lua((MOD:...)) marker."""
    # Vary timestamps so the bracketed prefix is unique-ish; not strictly
    # required — they only feed Entry.timestamp, not parsing.
    return (
        f"[16-04-26 00:00:{idx:02d}.000] LOG  : General      f:0, "
        f"t:1776297642{idx:03d}, st:48,648,157,434> "
        "Lua((MOD:Test Mod Alpha)) initialised."
    )


def _make_filler_line(idx: int) -> str:
    """A plain LOG-level entry with no marker; one raw line."""
    return (
        f"[16-04-26 00:01:{idx % 60:02d}.000] LOG  : General      f:0, "
        f"t:177629760{idx:04d}, st:48,648,200,178> filler entry {idx}."
    )


def _make_error_line() -> str:
    """A Lua-shaped ERROR with no Lua((MOD:...)) marker on the entry itself
    — so attribution must come from the lookback window if it comes at all."""
    return (
        "[16-04-26 00:02:00.000] ERROR: General      f:0, "
        "t:1776297900000, st:48,648,300,178> "
        "LuaManager.GetFunctionObject> no such function: doStuff"
    )


class RawLineLookbackTests(unittest.TestCase):
    """Phase 3 — lookback semantics measure raw file lines, not body-line
    budgets. Multi-line entries inside the window must not shrink the
    practical reach."""

    def _write_fixture(self, name: str, lines: list[str]) -> pathlib.Path:
        path = FIXTURE_DIR / name
        path.write_text("\n".join(lines) + "\n")
        return path

    def test_marker_exactly_at_lookback_boundary_attributes(self) -> None:
        # Marker on line 1, ERROR on line 41 -> raw-line distance = 40
        # (inclusive of INFERRED_LOOKBACK_LINES=40 -> still attributed).
        lines = [_make_marker_line(0)]
        for i in range(1, 40):
            lines.append(_make_filler_line(i))
        lines.append(_make_error_line())  # line 41 in the fixture
        path = self._write_fixture("_rawline_at_boundary.txt", lines)
        try:
            entries = pz_parser.parse_file(path)
            self.assertEqual(entries[0].line_start, 1)
            self.assertEqual(entries[-1].line_start, 41)
            records = pz_parser.classify_entries(entries, source_file="b1.txt")
            self.assertEqual(len(records), 1)
            self.assertEqual(records[0].attribution, "inferred")
            self.assertEqual(records[0].mod_id, "testmodalpha")
        finally:
            path.unlink()

    def test_marker_one_line_past_boundary_does_not_attribute(self) -> None:
        # Marker on line 1, ERROR on line 42 -> raw-line distance = 41
        # (just outside INFERRED_LOOKBACK_LINES -> unattributed).
        lines = [_make_marker_line(0)]
        for i in range(1, 41):
            lines.append(_make_filler_line(i))
        lines.append(_make_error_line())  # line 42 in the fixture
        path = self._write_fixture("_rawline_past_boundary.txt", lines)
        try:
            entries = pz_parser.parse_file(path)
            self.assertEqual(entries[0].line_start, 1)
            self.assertEqual(entries[-1].line_start, 42)
            records = pz_parser.classify_entries(entries, source_file="b2.txt")
            self.assertEqual(len(records), 1)
            self.assertEqual(records[0].attribution, "unattributed")
            self.assertEqual(records[0].mod_id, "__unattributed__")
        finally:
            path.unlink()

    def test_multiline_entry_does_not_shrink_practical_lookback(self) -> None:
        """Multi-line entries inside the lookback window do not break
        attribution. (Old body-line-budget and new raw-line-distance semantics
        happen to be equivalent on contiguous PZ entries; this test locks the
        post-fix semantic against future regression to a budget that *would*
        differ — e.g. a body-line cap with a smaller value.)
        """
        # Layout the file so a multi-line entry sits between marker and ERROR.
        # The marker on line 1 is within 40 raw lines of the ERROR even though
        # the file has a 6-line multi-line entry in between.
        lines = [_make_marker_line(0)]            # raw line 1: marker entry
        # Single-line fillers on raw lines 2..30 (29 entries).
        for i in range(1, 30):
            lines.append(_make_filler_line(i))
        # Multi-line entry: header on raw line 31, 5 continuations on lines
        # 32..36 (Java-stack-trace shape).
        lines.append(
            "[16-04-26 00:01:30.000] LOG  : General      f:0, "
            "t:1776297930000, st:48,648,200,178> stack trace dump"
        )
        for k in range(5):
            lines.append(f"\tat zombie.SomeClass.method{k}(SomeClass.java:{k + 1})")
        # Single-line fillers on raw lines 37..40 (4 entries).
        for i in range(30, 34):
            lines.append(_make_filler_line(i))
        # ERROR at raw line 41 -> N - 1 = 40 -> within window.
        lines.append(_make_error_line())
        path = self._write_fixture("_rawline_multiline.txt", lines)
        try:
            entries = pz_parser.parse_file(path)
            # Sanity-check the layout: first entry at line 1, multi-line entry
            # sits at line 31 with 6 body lines (header + 5 continuations),
            # ERROR at line 41.
            self.assertEqual(entries[0].line_start, 1)
            multi = next(
                e for e in entries
                if e.line_start == 31 and len(e.body) == 6
            )
            self.assertEqual(multi.line_end, 36)
            self.assertEqual(entries[-1].line_start, 41)
            records = pz_parser.classify_entries(entries, source_file="ml.txt")
            self.assertEqual(len(records), 1)
            # Raw-line-distance semantics: the marker on line 1 is 40 raw
            # lines from the ERROR on line 41, so attribution holds. (Old
            # body-line-budget would also pass here on contiguous entries;
            # this assertion locks the post-fix behavior against future
            # regression to a tighter cap.)
            self.assertEqual(records[0].attribution, "inferred")
            self.assertEqual(records[0].mod_id, "testmodalpha")
        finally:
            path.unlink()


if __name__ == "__main__":
    unittest.main()
