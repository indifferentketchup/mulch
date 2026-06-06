<?php

namespace IndifferentKetchup\CodexPz\Analysis\ProjectZomboid;

use IndifferentKetchup\CodexPz\Analysis\Information;
use IndifferentKetchup\CodexPz\Analysis\PatternInsightInterface;
use IndifferentKetchup\CodexPz\Pattern\ProjectZomboid\AdminPattern;

class AdminChangedOptionInformation extends Information implements PatternInsightInterface
{
    public static function getPatterns(): array
    {
        return [AdminPattern::CHANGED_OPTION_ENTRY];
    }

    public function setMatches(array $matches, mixed $patternKey): void
    {
        $this->setLabel('Admin changed option');
        $this->setValue(sprintf(
            '%s set %s=%s',
            $matches['admin'],
            $matches['option'],
            $matches['value']
        ));
    }
}
