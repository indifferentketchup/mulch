<?php

namespace IndifferentKetchup\CodexPz\Pattern\ProjectZomboid;

/**
 * Analyser extractor regexes for Lua-level warnings in DebugLog-server.txt.
 *
 * Named groups are safe here (analyser patterns only — never passed to PatternParser).
 * None of these patterns match entries containing "Exception thrown".
 *
 * Families:
 *   REQUIRE_FAILED    — require("path") failed
 *   FUNCTION_MISSING  — no such function "Name"
 *   RECURSIVE_REQUIRE — recursive require(): /path/to/file.lua
 */
class LuaWarningPattern
{
    public const string REQUIRE_FAILED = '/require\("(?<path>[^"]+)"\) failed/';

    public const string FUNCTION_MISSING = '/no such function "(?<name>[^"]+)"/';

    /** Lazy match stops before a sentence-ending dot at end of text/line. */
    public const string RECURSIVE_REQUIRE = '/recursive require\(\): (?<path>\S+?)\.?(?=\s|$)/';
}
