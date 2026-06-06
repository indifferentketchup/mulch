<?php

namespace IndifferentKetchup\CodexPz\Pattern\ProjectZomboid;

/**
 * Regex constants for the Project Zomboid item.txt format.
 *
 *   [time] steamid "player" location ±N x,y,z [Base.ItemId].
 */
class ItemPattern
{
    public const string LINE = '/^\[(\d{2}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\.\d{3})\] \d{17} "[^"]+" \S+ [+\-]\d+ \d+,\d+,\d+ \[[^\]]+\]\.$/';

    public const string FIELDS = '/^\[\d{2}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\.\d{3}\] (?<steamid>\d{17}) "(?<player>[^"]+)" (?<location>\S+) (?<delta>[+\-]\d+) (?<x>\d+),(?<y>\d+),(?<z>\d+) \[(?<item>[^\]]+)\]\.$/';
}
