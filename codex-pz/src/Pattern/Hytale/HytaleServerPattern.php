<?php

namespace IndifferentKetchup\CodexPz\Pattern\Hytale;

/**
 * Regex constants for the Hytale dedicated-server log format.
 *
 * Lines look like:
 *   [2026/05/07 12:34:56 INFO] [HytaleServer] Starting HytaleServer 1.0.0
 *
 * PREFIX captures, in order (unnamed — see CLAUDE.md Pitfall 1):
 *   1. full bracketed prefix    (e.g. "[2026/05/07 12:34:56 INFO]")
 *   2. timestamp                (e.g. "2026/05/07 12:34:56")
 *   3. level word               (e.g. "INFO" / "WARN" / "ERROR")
 *
 * The full LINE regex is built at runtime by HytaleLog::getPattern() —
 * this constant is the prefix portion only.
 */
class HytaleServerPattern
{
    public const string PREFIX = '(\[((?:[0-9]{2,4}\/?){3} (?:[0-9]{2}\:?){3})\s*(\w+)\])';
}
