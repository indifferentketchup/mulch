"""Tests for pz_parser phase 8 — signature computation."""
from __future__ import annotations

import pathlib
import sys
import unittest

sys.path.insert(0, str(pathlib.Path(__file__).resolve().parents[1]))

import pz_parser  # noqa: E402


class PatternIdStabilityTests(unittest.TestCase):
    """pattern_id should be invariant under formatting variations."""

    def test_pattern_id_collapses_numeric_runs(self) -> None:
        a = pz_parser.compute_pattern_id(
            "ERROR",
            "General  f:0, t:1776297642, st:48,648,157,434> failed at offset 12345",
        )
        b = pz_parser.compute_pattern_id(
            "ERROR",
            "General  f:0, t:9999999999, st:99,99,99,99> failed at offset 99999",
        )
        self.assertEqual(a, b)

    def test_pattern_id_collapses_quoted_strings_and_whitespace(self) -> None:
        a = pz_parser.compute_pattern_id(
            "ERROR",
            'no such function "doStuff"   in module',
        )
        b = pz_parser.compute_pattern_id(
            "ERROR",
            'no such function "fooBarBaz" in module',
        )
        # Whitespace-collapse plus quoted-string-flatten => same pattern_id.
        self.assertEqual(a, b)

    def test_pattern_id_changes_with_level(self) -> None:
        a = pz_parser.compute_pattern_id("ERROR", "exception thrown")
        b = pz_parser.compute_pattern_id("WARN", "exception thrown")
        self.assertNotEqual(a, b)


class SignatureUniquenessTests(unittest.TestCase):
    """signature should fan out across mods sharing a pattern_id."""

    def test_signature_unique_per_mod_for_shared_pattern(self) -> None:
        # Same first line, different mod_ids — different signatures, same pattern_id.
        pat = pz_parser.compute_pattern_id("ERROR", "Lua((MOD:X)) crash")
        sig_a = pz_parser.compute_signature(pat, "spongiesclothing")
        sig_b = pz_parser.compute_signature(pat, "testmodalpha")
        self.assertNotEqual(sig_a, sig_b)
        # Both should share their pattern_id (consumer's pattern-fanout view).
        self.assertEqual(pat[:7], "sha256:")


class SeverityPrefixStripTests(unittest.TestCase):
    """A body line that begins with a literal severity word (``SEVERE:``,
    ``ERROR:``, ``WARN:``, ``FATAL:``) should not fragment pattern_id away
    from the otherwise-identical body that lacks the prefix. The bracketed
    level already feeds pattern_id; the prefix is redundant and varies in
    practice."""

    def test_pattern_id_invariant_under_body_prefix_severe(self) -> None:
        # Same logical error: one line carries ``SEVERE: `` body prefix, the
        # other doesn't. Both classified as SEVERE by their bracketed level.
        with_prefix = pz_parser.compute_pattern_id(
            "SEVERE",
            "SEVERE: foo at zombie.X(File.java:42)",
        )
        without_prefix = pz_parser.compute_pattern_id(
            "SEVERE",
            "foo at zombie.X(File.java:42)",
        )
        self.assertEqual(with_prefix, without_prefix)

    def test_pattern_id_invariant_under_body_prefix_error(self) -> None:
        with_prefix = pz_parser.compute_pattern_id(
            "ERROR",
            "ERROR: doStuff failed in module",
        )
        without_prefix = pz_parser.compute_pattern_id(
            "ERROR",
            "doStuff failed in module",
        )
        self.assertEqual(with_prefix, without_prefix)


if __name__ == "__main__":
    unittest.main()
