<?php

namespace IndifferentKetchup\CodexPz\Pattern\ProjectZomboid;

/**
 * Regex constants for the Project Zomboid PerkLog.txt format.
 *
 *   [time] [steamid][player][x,y,z][event-or-perks][Hours Survived: N].
 *
 * The fourth bracketed field is either a single token (Login, Logout,
 * LevelUp, etc.) or a comma-separated list of Skill=N pairs. Both fit
 * the same character class.
 */
class PerkPattern
{
    public const string LINE = '/^\[(\d{2}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\.\d{3})\] \[\d{17}\]\[[^\]]+\]\[\d+,\d+,\d+\]\[[^\]]+\]\[Hours Survived: \d+\]\.$/';

    public const string FIELDS = '/^\[\d{2}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\.\d{3}\] \[(?<steamid>\d{17})\]\[(?<player>[^\]]+)\]\[(?<x>\d+),(?<y>\d+),(?<z>\d+)\]\[(?<event>[^\]]+)\]\[Hours Survived: (?<hours>\d+)\]\.$/';

    public const string PERK_PAIR = '/(?<skill>[A-Za-z_]+)=(?<level>\d+)/';
}
