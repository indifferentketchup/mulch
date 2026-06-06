<?php

namespace IndifferentKetchup\CodexPz\Analysis\ProjectZomboid;

use IndifferentKetchup\CodexPz\Analysis\EngineNoiseInsightInterface;
use IndifferentKetchup\CodexPz\Analysis\Information;
use IndifferentKetchup\CodexPz\Analysis\PatternInsightInterface;
use IndifferentKetchup\CodexPz\Analysis\Severity;
use IndifferentKetchup\CodexPz\Analysis\SeverityAwareInsightInterface;
use IndifferentKetchup\CodexPz\Pattern\ProjectZomboid\AssetWarningPattern;

class MissingIconInformation extends Information implements PatternInsightInterface, SeverityAwareInsightInterface, EngineNoiseInsightInterface
{
    public static function getPatterns(): array
    {
        return [AssetWarningPattern::MISSING_ICON];
    }

    public function setMatches(array $matches, mixed $patternKey): void
    {
        $this->setLabel('Missing icon');
        $this->setValue(trim($matches['icon']));
    }

    public function getSeverity(): Severity
    {
        return Severity::Noise;
    }
}
