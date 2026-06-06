<?php

namespace IndifferentKetchup\CodexPz\Analysis\ProjectZomboid;

use IndifferentKetchup\CodexPz\Analysis\EngineNoiseInsightInterface;
use IndifferentKetchup\CodexPz\Analysis\Information;
use IndifferentKetchup\CodexPz\Analysis\PatternInsightInterface;
use IndifferentKetchup\CodexPz\Analysis\Severity;
use IndifferentKetchup\CodexPz\Analysis\SeverityAwareInsightInterface;
use IndifferentKetchup\CodexPz\Pattern\ProjectZomboid\ConfigDriftPattern;

class UnknownItemParamInformation extends Information implements PatternInsightInterface, SeverityAwareInsightInterface, EngineNoiseInsightInterface
{
    public static function getPatterns(): array
    {
        return [ConfigDriftPattern::UNKNOWN_ITEM_PARAM];
    }

    public function setMatches(array $matches, mixed $patternKey): void
    {
        $this->setLabel('Unknown item param');
        $this->setValue($matches['param']);
    }

    public function getSeverity(): Severity
    {
        return Severity::Noise;
    }
}
