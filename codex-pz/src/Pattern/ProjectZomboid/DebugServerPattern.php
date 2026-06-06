<?php

namespace IndifferentKetchup\CodexPz\Pattern\ProjectZomboid;

/**
 * Regex constants for the Project Zomboid DebugLog-server.txt format.
 *
 * Three line shapes exist across build generations (all capture TIME, LEVEL, PREFIX in groups 1–3):
 *
 *   LINE_B41_B42 — `[ts] LEVEL: Prefix  f:N[, t:N], st:N,N,N,N> body`
 *     Build 41 includes `t:` microseconds; B42 drops it.
 *
 *   LINE_B4X     — `[ts] LEVEL: Prefix , <unix_ms>> <tick>> body`
 *     Build 41.78.x (comma + unix-millis + tick-counter shape).
 *
 *   LINE is a back-compat alias for LINE_B41_B42; callers that predated the
 *   multi-format split continue to compile unchanged.
 *
 * Analyser extractor regexes (FIELDS, MOD_LOAD, …) may use named groups safely
 * because they are never passed to PatternParser directly.
 */
class DebugServerPattern
{
    public const string LINE_B41_B42 = '/^\[(\d{2}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\.\d{3})\]\s+(\w+)\s*:\s+(\S+)\s+f:\d+(?:,\s+t:\d+)?,?\s+st:[\d,]+>\s+.*$/';

    public const string LINE_B4X = '/^\[(\d{2}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\.\d{3})\]\s+(\w+)\s*:\s+(\S+)\s*,\s+\d+>\s+[\d,]+>\s+.*$/';

    public const string LINE = self::LINE_B41_B42;

    public const string VERSION = '/version=(?<version>\S+) (?<hash>[a-f0-9]{40}) (?<date>\d{4}-\d{2}-\d{2}) (?<time>\d{2}:\d{2}:\d{2})/';

    public const string MOD_LOAD = '/loading (?<mod>[A-Za-z0-9_]+)\.?$/';

    public const string MOD_MISSING = '/required mod "(?<mod>[^"]+)" not found/';

    public const string EXCEPTION_HEADER = '/^\[\d{2}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\.\d{3}\]\s+ERROR:.*Exception thrown/';

    public const string EXCEPTION = '/^\[\d{2}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\.\d{3}\][^\n]+Exception thrown\n\t(?<type>[A-Za-z0-9_.$]+(?:Exception|Error))[^\n]*(?<body>(?:\n\t.+)*)/';
}
