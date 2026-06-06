<?php

namespace IndifferentKetchup\CodexPz\Analysis\ProjectZomboid;

use IndifferentKetchup\CodexPz\Analysis\Information;
use IndifferentKetchup\CodexPz\Analysis\PatternInsightInterface;
use IndifferentKetchup\CodexPz\Pattern\ProjectZomboid\AdminPattern;

class AdminGrantedAccessInformation extends Information implements PatternInsightInterface
{
    public static function getPatterns(): array
    {
        return [AdminPattern::GRANTED_ACCESS_ENTRY];
    }

    public function setMatches(array $matches, mixed $patternKey): void
    {
        $this->setLabel('Admin granted access');
        $this->setValue(sprintf(
            '%s granted %s to %s',
            $matches['admin'],
            $matches['level'],
            $matches['target']
        ));
    }
}
