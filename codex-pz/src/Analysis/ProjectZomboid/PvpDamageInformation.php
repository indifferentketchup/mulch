<?php

namespace IndifferentKetchup\CodexPz\Analysis\ProjectZomboid;

use IndifferentKetchup\CodexPz\Analysis\Information;
use IndifferentKetchup\CodexPz\Analysis\PatternInsightInterface;
use IndifferentKetchup\CodexPz\Pattern\ProjectZomboid\PvpPattern;

class PvpDamageInformation extends Information implements PatternInsightInterface
{
    public static function getPatterns(): array
    {
        return [PvpPattern::COMBAT_REAL];
    }

    public function setMatches(array $matches, mixed $patternKey): void
    {
        $this->setLabel('PvP combat');
        $this->setValue(sprintf(
            '%s hit %s with %s',
            $matches['attacker'],
            $matches['victim'],
            $matches['weapon']
        ));
    }
}
