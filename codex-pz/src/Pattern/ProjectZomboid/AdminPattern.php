<?php

namespace IndifferentKetchup\CodexPz\Pattern\ProjectZomboid;

/**
 * Regex constants for the Project Zomboid admin.txt format.
 *
 * Free-form English: '[time] <admin> <verb> ...' Verb dispatch is left
 * to the analyser layer. The admin name itself can contain parentheses
 * (Nathan(Weerd)) or whitespace (silly goose) so the parser captures
 * only the timestamp.
 */
class AdminPattern
{
    public const string LINE = '/^\[(\d{2}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\.\d{3})\] .+\.$/';

    public const string ADDED_ITEM = '/^(?<admin>.+?) added item (?<item>Base\.\S+) in (?<target>.+?)\'s inventory$/';

    public const string ADDED_XP = '/^(?<admin>.+?) added (?<amount>[\d.]+) (?<skill>\S+) xp\'s to (?<target>.+)$/';

    public const string GRANTED_ACCESS = '/^(?<admin>.+?) granted (?<level>\w+) access level on (?<target>.+)$/';

    public const string CHANGED_OPTION = '/^(?<admin>.+?) changed option (?<option>\S+?)=(?<value>.+)$/';

    public const string RELOADED_OPTIONS = '/^(?<admin>.+?) reloaded options$/';

    public const string TELEPORTED = '/^(?<admin>.+?) teleported (?<target>.+?) to (?<x>\d+),(?<y>\d+),(?<z>-?\d+)$/';

    /**
     * Entry-anchored variants for analyser use. PatternAnalyser passes the
     * full Entry text (including the [time] prefix) to preg_match_all, so
     * these include the timestamp prefix and are anchored at start/end of
     * the line. The body-only constants above are kept for direct-message
     * matching.
     */
    public const string ADDED_ITEM_ENTRY = '/^\[\d{2}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\.\d{3}\] (?<admin>.+?) added item (?<item>Base\.\S+) in (?<target>.+?)\'s inventory\.?$/';

    public const string ADDED_XP_ENTRY = '/^\[\d{2}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\.\d{3}\] (?<admin>.+?) added (?<amount>[\d.]+) (?<skill>\S+) xp\'s to (?<target>.+?)\.?$/';

    public const string GRANTED_ACCESS_ENTRY = '/^\[\d{2}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\.\d{3}\] (?<admin>.+?) granted (?<level>\w+) access level on (?<target>.+?)\.?$/';

    public const string CHANGED_OPTION_ENTRY = '/^\[\d{2}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\.\d{3}\] (?<admin>.+?) changed option (?<option>\S+?)=(?<value>.+?)\.?$/';

    public const string RELOADED_OPTIONS_ENTRY = '/^\[\d{2}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\.\d{3}\] (?<admin>.+?) reloaded options\.?$/';

    public const string TELEPORTED_ENTRY = '/^\[\d{2}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\.\d{3}\] (?<admin>.+?) teleported (?<target>.+?) to (?<x>\d+),(?<y>\d+),(?<z>-?\d+)\.?$/';
}
