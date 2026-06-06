<?php

namespace IndifferentKetchup\CodexPz\Pattern\ProjectZomboid;

/**
 * Regex constants for the Project Zomboid user.txt format.
 *
 * Two row variants share the file: low-level connection events
 *   [time] Connection {add|disconnect} index=N guid=N id={N|null}.
 * and player join/leave events
 *   [time] steamid "player" {attempting|allowed} to join.
 *
 * Both variants are accepted by LINE, which captures only the timestamp.
 */
class UserPattern
{
    public const string LINE = '/^\[(\d{2}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\.\d{3})\] (?:Connection (?:add|disconnect) index=\d+ guid=\d+ id=(?:\d+|null)|\d{17} "[^"]+" .+?)\.?$/';

    public const string CONNECTION = '/^\[\d{2}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\.\d{3}\] Connection (?<action>add|disconnect) index=(?<index>\d+) guid=(?<guid>\d+) id=(?<id>\d+|null)\.?$/';

    public const string PLAYER_EVENT = '/^\[\d{2}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\.\d{3}\] (?<steamid>\d{17}) "(?<player>[^"]+)" (?<event>.+?)\.?$/';
}
