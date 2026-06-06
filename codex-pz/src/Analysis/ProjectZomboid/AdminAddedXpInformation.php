<?php

namespace IndifferentKetchup\CodexPz\Analysis\ProjectZomboid;

use IndifferentKetchup\CodexPz\Analysis\Information;
use IndifferentKetchup\CodexPz\Analysis\PatternInsightInterface;
use IndifferentKetchup\CodexPz\Pattern\ProjectZomboid\AdminPattern;

class AdminAddedXpInformation extends Information implements PatternInsightInterface
{
    public static function getPatterns(): array
    {
        return [AdminPattern::ADDED_XP_ENTRY];
    }

    public function setMatches(array $matches, mixed $patternKey): void
    {
        $this->setLabel('Admin added xp');
        $this->setValue(sprintf(
            '%s added %s %s xp to %s',
            $matches['admin'],
            $matches['amount'],
            $matches['skill'],
            $matches['target']
        ));
    }
}
