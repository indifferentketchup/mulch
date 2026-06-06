<?php

namespace IndifferentKetchup\CodexPz\Analysis\ProjectZomboid;

use IndifferentKetchup\CodexPz\Analysis\Information;
use IndifferentKetchup\CodexPz\Analysis\PatternInsightInterface;
use IndifferentKetchup\CodexPz\Pattern\ProjectZomboid\AdminPattern;

class AdminAddedItemInformation extends Information implements PatternInsightInterface
{
    public static function getPatterns(): array
    {
        return [AdminPattern::ADDED_ITEM_ENTRY];
    }

    public function setMatches(array $matches, mixed $patternKey): void
    {
        $this->setLabel('Admin added item');
        $this->setValue(sprintf(
            '%s added %s to %s',
            $matches['admin'],
            $matches['item'],
            $matches['target']
        ));
    }
}
