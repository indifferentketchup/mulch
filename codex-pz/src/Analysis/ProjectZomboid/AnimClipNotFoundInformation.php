<?php

namespace IndifferentKetchup\CodexPz\Analysis\ProjectZomboid;

use IndifferentKetchup\CodexPz\Analysis\Information;
use IndifferentKetchup\CodexPz\Analysis\PatternInsightInterface;
use IndifferentKetchup\CodexPz\Analysis\Severity;
use IndifferentKetchup\CodexPz\Analysis\SeverityAwareInsightInterface;
use IndifferentKetchup\CodexPz\Pattern\ProjectZomboid\AnimationWarningPattern;

class AnimClipNotFoundInformation extends Information implements PatternInsightInterface, SeverityAwareInsightInterface
{
    public static function getPatterns(): array
    {
        return [AnimationWarningPattern::ANIM_CLIP_NOT_FOUND];
    }

    public function setMatches(array $matches, mixed $patternKey): void
    {
        $this->setLabel('Anim clip not found');
        $this->setValue(trim($matches['clip']));
    }

    public function getSeverity(): Severity
    {
        return Severity::Low;
    }
}
