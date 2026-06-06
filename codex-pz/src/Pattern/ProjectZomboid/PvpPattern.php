<?php

namespace IndifferentKetchup\CodexPz\Pattern\ProjectZomboid;

/**
 * Regex constants for the Project Zomboid pvp.txt format.
 *
 * Two row variants share the file: Safe House toggles
 *   [time][LOG] Safety: "player" (x,y,z) restore true.
 * and Combat events
 *   [time][INFO] Combat: "attacker" (x,y,z) hit "victim" (x,y,z) weapon="W" damage=N.NN.
 *
 * Z coordinates can be negative (basement levels: -1, -2).
 *
 * LINE captures, in order:
 *   1. time
 *   2. level     (LOG | INFO | WARN | ERROR)
 *   3. subsystem (Safety | Combat)
 */
class PvpPattern
{
    public const string LINE = '/^\[(\d{2}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\.\d{3})\]\[(LOG|INFO|WARN|ERROR)\] (\w+): .+\.$/';

    public const string COMBAT = '/^Combat: "(?<attacker>[^"]+)" \((?<ax>\d+),(?<ay>\d+),(?<az>-?\d+)\) hit "(?<victim>[^"]+)" \((?<vx>\d+),(?<vy>\d+),(?<vz>-?\d+)\) weapon="(?<weapon>[^"]+)" damage=(?<damage>-?\d+\.\d+)\.$/';

    public const string SAFETY = '/^Safety: "(?<player>[^"]+)" \((?<x>\d+),(?<y>\d+),(?<z>-?\d+)\) (?<verb>\w+) (?<state>true|false)\.$/';

    /**
     * Real-PvP combat: weapon!="zombie" AND damage>0. Filtering is in the
     * regex itself so PatternAnalyser produces no insights for zombie/zero
     * rows. Damage clause matches any positive non-zero float (rejects
     * 0.000000 and any leading-minus value).
     */
    public const string COMBAT_REAL = '/Combat: "(?<attacker>[^"]+)" \([^)]+\) hit "(?<victim>[^"]+)" \([^)]+\) weapon="(?<weapon>(?!zombie")[^"]+)" damage=(?<damage>0\.0*[1-9][0-9]*|[1-9][0-9]*\.[0-9]+)/';
}
