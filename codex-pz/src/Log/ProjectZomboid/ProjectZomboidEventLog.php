<?php

namespace IndifferentKetchup\CodexPz\Log\ProjectZomboid;

/**
 * Marker base for ProjectZomboid logs whose entries are strictly one line
 * each (the ten structured event files: admin, BurdJournals, chat,
 * ClientActionLog, cmd, item, map, PerkLog, pvp, user). Distinct from
 * ProjectZomboidServerLog, which permits multi-line entries
 * (DebugLog-server stack traces).
 */
abstract class ProjectZomboidEventLog extends ProjectZomboidLog
{
}
