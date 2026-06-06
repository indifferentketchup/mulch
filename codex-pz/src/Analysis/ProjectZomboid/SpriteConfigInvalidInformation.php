<?php

namespace IndifferentKetchup\CodexPz\Analysis\ProjectZomboid;

use IndifferentKetchup\CodexPz\Analysis\Information;
use IndifferentKetchup\CodexPz\Analysis\PatternInsightInterface;
use IndifferentKetchup\CodexPz\Analysis\Severity;
use IndifferentKetchup\CodexPz\Analysis\SeverityAwareInsightInterface;
use IndifferentKetchup\CodexPz\Pattern\ProjectZomboid\AssetWarningPattern;

class SpriteConfigInvalidInformation extends Information implements PatternInsightInterface, SeverityAwareInsightInterface
{
    public static function getPatterns(): array
    {
        return [AssetWarningPattern::SPRITE_CONFIG_INVALID];
    }

    public function setMatches(array $matches, mixed $patternKey): void
    {
        $this->setLabel('Invalid sprite config');
        $this->setValue(trim($matches['object']));
    }

    public function getSeverity(): Severity
    {
        return Severity::Low;
    }
}
