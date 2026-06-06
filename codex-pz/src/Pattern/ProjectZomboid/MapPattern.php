<?php

namespace IndifferentKetchup\CodexPz\Pattern\ProjectZomboid;

/**
 * Regex constants for the Project Zomboid map.txt format.
 *
 *   [time] steamid "player" verb object at x,y,z.
 *
 * Coordinates may be integer or floating point; objects may be Base.X
 * tokens or 'IsoObject (X)' parenthesised forms.
 */
class MapPattern
{
    public const string LINE = '/^\[(\d{2}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\.\d{3})\] \d{17} "[^"]+" \S+ .+ at [\d.]+,[\d.]+,[\d.]+\.$/';

    public const string FIELDS = '/^\[\d{2}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\.\d{3}\] (?<steamid>\d{17}) "(?<player>[^"]+)" (?<verb>\S+) (?<object>.+) at (?<x>[\d.]+),(?<y>[\d.]+),(?<z>[\d.]+)\.$/';
}
