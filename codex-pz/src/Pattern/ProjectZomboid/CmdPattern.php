<?php

namespace IndifferentKetchup\CodexPz\Pattern\ProjectZomboid;

/**
 * Regex constants for the Project Zomboid cmd.txt format.
 *
 *   [time] steamid "player" command @ x,y,z.
 *
 * LINE captures, in order:
 *   1. time
 */
class CmdPattern
{
    public const string LINE = '/^\[(\d{2}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\.\d{3})\] \d{17} "[^"]+" \S+ @ \d+,\d+,\d+\.$/';

    public const string FIELDS = '/^\[\d{2}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\.\d{3}\] (?<steamid>\d{17}) "(?<player>[^"]+)" (?<command>\S+) @ (?<x>\d+),(?<y>\d+),(?<z>\d+)\.$/';
}
