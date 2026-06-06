<?php

namespace IndifferentKetchup\CodexPz\Pattern\ProjectZomboid;

/**
 * Regex constants for the Project Zomboid ClientActionLog.txt format.
 *
 * Strict 5-field bracketed structure:
 *   [time] [steamid][action][player][x,y,z][param].
 *
 * LINE captures, in order:
 *   1. time (DD-MM-YY HH:MM:SS.mmm)
 *
 * The remaining bracketed fields are extractable via FIELDS for analysers.
 */
class ClientActionPattern
{
    public const string LINE = '/^\[(\d{2}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\.\d{3})\] \[\d{17}\]\[[^\]]+\]\[[^\]]+\]\[\d+,\d+,\d+\]\[[^\]]+\]\.$/';

    public const string FIELDS = '/^\[\d{2}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\.\d{3}\] \[(?<steamid>\d{17})\]\[(?<action>[^\]]+)\]\[(?<player>[^\]]+)\]\[(?<x>\d+),(?<y>\d+),(?<z>\d+)\]\[(?<param>[^\]]+)\]\.$/';
}
