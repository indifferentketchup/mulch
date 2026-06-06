<?php

namespace IndifferentKetchup\CodexPz\Pattern\Hytale;

/**
 * Regex constants for the Hytale client log format.
 *
 * Lines look like:
 *   2026-05-07 12:34:56.0001|INFO|HytaleClient.Application.Program: Starting HytaleClient
 *
 * PREFIX captures, in order (unnamed — see CLAUDE.md Pitfall 1):
 *   1. full bar-delimited prefix (e.g. "2026-05-07 12:34:56.0001|INFO|")
 *   2. timestamp                 (e.g. "2026-05-07 12:34:56")
 *   3. level word                (e.g. "INFO" / "WARN" / "ERROR")
 *
 * The four-digit fractional-second tail after the timestamp is consumed by
 * the prefix but not captured. The full LINE regex is built at runtime by
 * HytaleLog::getPattern().
 */
class HytaleClientPattern
{
    public const string PREFIX = '(((?:[0-9]{2,4}\-?){3}\s(?:[0-9]{2}:?){3})\.[0-9]{4}\|(\w+)\|)';
}
