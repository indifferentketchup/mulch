"""Tests for pz_parser parsing pipeline (phases 1, 2, 4-7, 9)."""
from __future__ import annotations

import pathlib
import sys
import unittest

# Make the parser module importable when running via `python -m unittest
# discover -s tools/pz-analyzer/tests`.
sys.path.insert(0, str(pathlib.Path(__file__).resolve().parents[1]))

import pz_parser  # noqa: E402

FIXTURE_DIR = pathlib.Path(__file__).resolve().parent / "fixtures"


def fixture(name: str) -> pathlib.Path:
    return FIXTURE_DIR / name


class ParseFileTests(unittest.TestCase):
    """Phase 0 — basic line-shape recognition and continuation folding."""

    def test_parse_file_groups_continuations_under_entry(self) -> None:
        entries = pz_parser.parse_file(fixture("fixture_java_exception.txt"))
        # 3 bracketed entries; the ERROR has 4 continuation lines.
        self.assertEqual(len(entries), 3)
        error_entry = entries[1]
        self.assertEqual(error_entry.level, "ERROR")
        self.assertGreater(len(error_entry.body), 1)
        # First continuation should be the java exception line.
        self.assertIn("NoSuchFileException", error_entry.body[1])

    def test_parse_file_handles_empty_file(self) -> None:
        self.assertEqual(pz_parser.parse_file(fixture("fixture_empty.txt")), [])

    def test_parse_file_handles_no_errors(self) -> None:
        entries = pz_parser.parse_file(fixture("fixture_no_errors.txt"))
        self.assertEqual(len(entries), 3)
        self.assertTrue(all(e.level == "LOG" for e in entries))


class SeverityRecognitionTests(unittest.TestCase):
    """Phase 1 — ERROR / WARN / SEVERE recognition."""

    def test_classify_picks_up_error_warn_and_severe(self) -> None:
        entries = pz_parser.parse_file(fixture("fixture_severity_variants.txt"))
        records = pz_parser.classify_entries(entries, source_file="severity.txt")
        levels = sorted({r.level for r in records})
        # Spec accepts ERROR / WARN / SEVERE. The third entry has bracketed
        # ERROR but body starts with SEVERE: ; effective_level should be SEVERE.
        self.assertIn("ERROR", levels)
        self.assertIn("WARN", levels)
        self.assertIn("SEVERE", levels)

    def test_log_lines_are_ignored(self) -> None:
        entries = pz_parser.parse_file(fixture("fixture_no_errors.txt"))
        records = pz_parser.classify_entries(entries, source_file="x.txt")
        self.assertEqual(records, [])


class StackCollectionTests(unittest.TestCase):
    """Phase 2 — bidirectional stack collection."""

    def test_pre_stack_walk_picks_up_preceding_lua_frames(self) -> None:
        entries = pz_parser.parse_file(fixture("fixture_pre_stack.txt"))
        # The ERROR entry is the 5th LOG-bracketed line; its predecessors are
        # LOG-bracketed entries whose bodies are stack-shaped lines.
        records = pz_parser.classify_entries(entries, source_file="pre.txt")
        self.assertEqual(len(records), 1)
        rec = records[0]
        # Pre-stack walk should pick up at least the "at media/lua/.../A.lua:11" frame.
        self.assertTrue(any("A.lua:11" in f for f in rec.stack))

    def test_post_stack_collected_from_entry_body_continuations(self) -> None:
        entries = pz_parser.parse_file(fixture("fixture_post_stack.txt"))
        records = pz_parser.classify_entries(entries, source_file="post.txt")
        self.assertEqual(len(records), 1)
        rec = records[0]
        self.assertTrue(any("X.lua:11" in f for f in rec.stack))
        self.assertTrue(any("Y.lua:22" in f for f in rec.stack))
        # Lua [string "..."]:N form preserves quoting in the captured frame.
        self.assertTrue(any("Z.lua" in f and ":33" in f for f in rec.stack))

    def test_stack_capped_at_eight_frames(self) -> None:
        # Synthesise an ERROR with many continuation frames.
        lines = ["[16-04-26 00:00:42.314] ERROR: General      f:0, t:1, st:1,2,3,4> Lua((MOD:Test Mod Alpha)) crash"]
        for i in range(20):
            lines.append(f"\tat media/lua/client/F{i}.lua:{i + 1}")
        path = FIXTURE_DIR / "_runtime_stack_cap.txt"
        path.write_text("\n".join(lines) + "\n")
        try:
            entries = pz_parser.parse_file(path)
            records = pz_parser.classify_entries(entries, source_file="cap.txt")
            self.assertEqual(len(records), 1)
            self.assertLessEqual(len(records[0].stack), pz_parser.MAX_STACK_FRAMES)
            # And it should be exactly MAX_STACK_FRAMES given >MAX inputs.
            self.assertEqual(len(records[0].stack), pz_parser.MAX_STACK_FRAMES)
        finally:
            path.unlink()


class FileLineExtractionTests(unittest.TestCase):
    """Phase 4 — five-fallback file:line extraction."""

    def test_each_fallback_form_extracts_path(self) -> None:
        entries = pz_parser.parse_file(fixture("fixture_file_line_fallbacks.txt"))
        records = pz_parser.classify_entries(entries, source_file="ff.txt")
        # 5 distinct ERRORs, distinct mods — should produce 5 records.
        files = sorted(r.file for r in records)
        self.assertEqual(
            files,
            sorted([
                "media/lua/client/F1.lua",
                "media/lua/client/F2.lua",
                "media/lua/client/F3.lua",
                "media/lua/client/F4.lua",
                "media/lua/client/F5.lua",
            ]),
        )

    def test_quoted_path_without_line_number_yields_zero(self) -> None:
        # Format 4 fixture line lacks a :NN suffix on the quoted path.
        file_path, line_no = pz_parser.extract_file_line(
            'failure about "media/lua/client/F4.lua" tail'
        )
        self.assertEqual(file_path, "media/lua/client/F4.lua")
        self.assertEqual(line_no, 0)


class CauseChainTests(unittest.TestCase):
    """Phase 5 — Caused-by chain unwinding."""

    def test_caused_by_chain_renders_with_arrow_separator(self) -> None:
        entries = pz_parser.parse_file(fixture("fixture_cause_chain.txt"))
        records = pz_parser.classify_entries(entries, source_file="cc.txt")
        self.assertEqual(len(records), 1)
        chain = records[0].cause_chain
        self.assertIn("RuntimeException", chain)
        self.assertIn("IllegalStateException", chain)
        self.assertIn("NullPointerException", chain)
        # Order preserved (outer -> inner).
        idx_runtime = chain.index("RuntimeException")
        idx_illegal = chain.index("IllegalStateException")
        idx_null = chain.index("NullPointerException")
        self.assertLess(idx_runtime, idx_illegal)
        self.assertLess(idx_illegal, idx_null)

    def test_no_cause_chain_when_no_exceptions(self) -> None:
        entries = pz_parser.parse_file(fixture("fixture_unattributed.txt"))
        records = pz_parser.classify_entries(entries, source_file="u.txt")
        self.assertEqual(len(records), 1)
        self.assertEqual(records[0].cause_chain, "")


class KindDetectionTests(unittest.TestCase):
    """Phases 6 & 7 — kind classification."""

    def test_java_exception_kind_when_no_lua_marker(self) -> None:
        entries = pz_parser.parse_file(fixture("fixture_java_exception.txt"))
        records = pz_parser.classify_entries(entries, source_file="je.txt")
        self.assertEqual(len(records), 1)
        self.assertEqual(records[0].kind, "java_exception")
        # Java engine errors should resolve to __unattributed__.
        self.assertEqual(records[0].mod_id, "__unattributed__")

    def test_engine_noise_kind_for_kahluathread(self) -> None:
        entries = pz_parser.parse_file(fixture("fixture_engine_noise.txt"))
        records = pz_parser.classify_entries(entries, source_file="en.txt")
        self.assertEqual(len(records), 1)
        self.assertEqual(records[0].kind, "engine_noise")

    def test_lua_runtime_kind_for_attributed_lua_error(self) -> None:
        entries = pz_parser.parse_file(fixture("fixture_lua_attributed.txt"))
        records = pz_parser.classify_entries(entries, source_file="la.txt")
        self.assertEqual(len(records), 1)
        self.assertEqual(records[0].kind, "lua_runtime")

    def test_require_failed_kind(self) -> None:
        entries = pz_parser.parse_file(fixture("fixture_require_failed.txt"))
        records = pz_parser.classify_entries(entries, source_file="rf.txt")
        self.assertEqual(len(records), 1)
        self.assertEqual(records[0].kind, "require_failed")


class AggregationTests(unittest.TestCase):
    """Phase 9 — dedup, occurrence_count, files-set growth."""

    def test_three_identical_errors_dedup_to_one_record(self) -> None:
        entries = pz_parser.parse_file(fixture("fixture_dedup.txt"))
        records = pz_parser.classify_entries(entries, source_file="dd.txt")
        self.assertEqual(len(records), 1)
        self.assertEqual(records[0].occurrence_count, 3)
        # files list shouldn't duplicate "dd.txt".
        self.assertEqual(records[0].files, ["dd.txt"])


if __name__ == "__main__":
    unittest.main()
