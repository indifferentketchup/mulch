<?php

namespace IndifferentKetchup\CodexPz\Analysis\ProjectZomboid;

use IndifferentKetchup\CodexPz\Analysis\Information;
use IndifferentKetchup\CodexPz\Analysis\PatternInsightInterface;
use IndifferentKetchup\CodexPz\Analysis\Severity;
use IndifferentKetchup\CodexPz\Analysis\SeverityAwareInsightInterface;
use IndifferentKetchup\CodexPz\Pattern\ProjectZomboid\ConfigDriftPattern;

class UnknownSandboxOptionInformation extends Information implements PatternInsightInterface, SeverityAwareInsightInterface
{
    public static function getPatterns(): array
    {
        return [ConfigDriftPattern::UNKNOWN_SANDBOX_OPTION];
    }

    public function setMatches(array $matches, mixed $patternKey): void
    {
        $this->setLabel('Unknown sandbox option');
        $this->setValue($matches['option']);
    }

    public function getSeverity(): Severity
    {
        return Severity::Low;
    }
}
