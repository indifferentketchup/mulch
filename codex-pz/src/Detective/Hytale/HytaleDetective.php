<?php

namespace IndifferentKetchup\CodexPz\Detective\Hytale;

use IndifferentKetchup\CodexPz\Detective\Detective;
use IndifferentKetchup\CodexPz\Log\Hytale\HytaleClientLog;
use IndifferentKetchup\CodexPz\Log\Hytale\HytaleServerLog;

/**
 * Pre-registers the two Hytale log classes (Server, Client) so that
 * detect() can dispatch among them on first-line content signature.
 */
class HytaleDetective extends Detective
{
    public function __construct()
    {
        $this->addPossibleLogClass(HytaleServerLog::class);
        $this->addPossibleLogClass(HytaleClientLog::class);
    }
}
