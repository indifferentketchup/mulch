<?php

namespace IndifferentKetchup\CodexPz\Analysis\ProjectZomboid;

use IndifferentKetchup\CodexPz\Analysis\Information;
use IndifferentKetchup\CodexPz\Analysis\PatternInsightInterface;
use IndifferentKetchup\CodexPz\Analysis\Severity;
use IndifferentKetchup\CodexPz\Analysis\SeverityAwareInsightInterface;
use IndifferentKetchup\CodexPz\Pattern\ProjectZomboid\DebugServerPattern;

class EngineVersionInformation extends Information implements PatternInsightInterface, SeverityAwareInsightInterface
{
    public static function getPatterns(): array
    {
        return [DebugServerPattern::VERSION];
    }

    public function getSeverity(): Severity
    {
        return Severity::Low;
    }

    public function setMatches(array $matches, mixed $patternKey): void
    {
        $this->setLabel('Engine version');
        $this->setValue(sprintf(
            '%s (build %s, %s %s)',
            $matches['version'],
            $matches['hash'],
            $matches['date'],
            $matches['time']
        ));
    }
}
