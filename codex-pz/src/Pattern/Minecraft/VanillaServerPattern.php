<?php

namespace IndifferentKetchup\CodexPz\Pattern\Minecraft;

/**
 * Vanilla Minecraft server log line pattern + detection banner.
 *
 * Lines look like:
 *   [12:34:56] [Server thread/INFO]: Starting minecraft server version 1.21.4
 *
 * LINE captures, in order (unnamed — see CLAUDE.md Pitfall 1):
 *   1. PREFIX  — the bracket-delimited timestamp+thread+level header
 *                (e.g. "[12:34:56] [Server thread/INFO]:")
 *   2. LEVEL   — the level token (INFO, WARN, ERROR, ...) — nested inside PREFIX
 *
 * The HH:MM:SS timestamp is folded into PREFIX rather than separately
 * captured; this matches upstream Aternos\Codex\Minecraft\Log\Minecraft\
 * Vanilla\VanillaLog::$pattern behaviour byte-for-byte.
 *
 * DETECTION_BANNER is a substring regex that matches the conventional first
 * line of a Vanilla server log; FirstLinesPatternDetector uses it to score
 * a log as Vanilla-server with high weight.
 */
class VanillaServerPattern
{
    public const string LINE = '/^(\[(?:[0-9]{2}\:?){3}\] \[[^\/]+\/(\w+)\]\:).*$/';

    public const string DETECTION_BANNER = '/Starting minecraft server version/';
}
