<?php

namespace IndifferentKetchup\CodexPz\Analysis\ProjectZomboid;

use IndifferentKetchup\CodexPz\Analysis\Information;
use IndifferentKetchup\CodexPz\Analysis\PatternInsightInterface;
use IndifferentKetchup\CodexPz\Pattern\ProjectZomboid\AdminPattern;

class AdminTeleportedInformation extends Information implements PatternInsightInterface
{
    public static function getPatterns(): array
    {
        return [AdminPattern::TELEPORTED_ENTRY];
    }

    public function setMatches(array $matches, mixed $patternKey): void
    {
        $this->setLabel('Admin teleported');
        $this->setValue(sprintf(
            '%s teleported %s to %s,%s,%s',
            $matches['admin'],
            $matches['target'],
            $matches['x'],
            $matches['y'],
            $matches['z']
        ));
    }
}
