<?php

namespace IndifferentKetchup\CodexPz\Pattern\ProjectZomboid;

/**
 * Regex constants for the Project Zomboid BurdJournals.txt format.
 *
 *   [time] [BurdJournals] LEVEL: message.
 *
 * LINE captures, in order:
 *   1. time
 *   2. tag    (e.g. BurdJournals)
 *   3. level  (WARNING | ERROR | INFO | DEBUG)
 */
class BurdJournalsPattern
{
    public const string LINE = '/^\[(\d{2}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\.\d{3})\] \[(\w+)\] (WARNING|ERROR|INFO|DEBUG): .+\.?$/';
}
