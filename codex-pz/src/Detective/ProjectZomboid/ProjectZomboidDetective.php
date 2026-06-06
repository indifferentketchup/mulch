<?php

namespace IndifferentKetchup\CodexPz\Detective\ProjectZomboid;

use IndifferentKetchup\CodexPz\Detective\Detective;
use IndifferentKetchup\CodexPz\Log\ProjectZomboid\ProjectZomboidAdminLog;
use IndifferentKetchup\CodexPz\Log\ProjectZomboid\ProjectZomboidBurdJournalsLog;
use IndifferentKetchup\CodexPz\Log\ProjectZomboid\ProjectZomboidChatLog;
use IndifferentKetchup\CodexPz\Log\ProjectZomboid\ProjectZomboidClientActionLog;
use IndifferentKetchup\CodexPz\Log\ProjectZomboid\ProjectZomboidCmdLog;
use IndifferentKetchup\CodexPz\Log\ProjectZomboid\ProjectZomboidItemLog;
use IndifferentKetchup\CodexPz\Log\ProjectZomboid\ProjectZomboidMapLog;
use IndifferentKetchup\CodexPz\Log\ProjectZomboid\ProjectZomboidPerkLog;
use IndifferentKetchup\CodexPz\Log\ProjectZomboid\ProjectZomboidPvpLog;
use IndifferentKetchup\CodexPz\Log\ProjectZomboid\ProjectZomboidServerLog;
use IndifferentKetchup\CodexPz\Log\ProjectZomboid\ProjectZomboidUserLog;

/**
 * Pre-registers all eleven ProjectZomboid log classes so that detect()
 * can dispatch among them on filename hint + content signature.
 */
class ProjectZomboidDetective extends Detective
{
    public function __construct()
    {
        $this->addPossibleLogClass(ProjectZomboidServerLog::class);
        $this->addPossibleLogClass(ProjectZomboidChatLog::class);
        $this->addPossibleLogClass(ProjectZomboidClientActionLog::class);
        $this->addPossibleLogClass(ProjectZomboidCmdLog::class);
        $this->addPossibleLogClass(ProjectZomboidItemLog::class);
        $this->addPossibleLogClass(ProjectZomboidMapLog::class);
        $this->addPossibleLogClass(ProjectZomboidPerkLog::class);
        $this->addPossibleLogClass(ProjectZomboidPvpLog::class);
        $this->addPossibleLogClass(ProjectZomboidAdminLog::class);
        $this->addPossibleLogClass(ProjectZomboidUserLog::class);
        $this->addPossibleLogClass(ProjectZomboidBurdJournalsLog::class);
    }
}
