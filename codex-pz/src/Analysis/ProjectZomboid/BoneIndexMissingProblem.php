<?php

namespace IndifferentKetchup\CodexPz\Analysis\ProjectZomboid;

use IndifferentKetchup\CodexPz\Analysis\InsightInterface;
use IndifferentKetchup\CodexPz\Analysis\PatternInsightInterface;
use IndifferentKetchup\CodexPz\Analysis\Problem;
use IndifferentKetchup\CodexPz\Analysis\Severity;
use IndifferentKetchup\CodexPz\Analysis\SeverityAwareInsightInterface;
use IndifferentKetchup\CodexPz\Pattern\ProjectZomboid\AnimationWarningPattern;

class BoneIndexMissingProblem extends Problem implements PatternInsightInterface, SeverityAwareInsightInterface
{
    private string $node = '';

    public static function getPatterns(): array
    {
        return [AnimationWarningPattern::BONE_INDEX_MISSING];
    }

    public function setMatches(array $matches, mixed $patternKey): void
    {
        $this->node = $matches['node'];
    }

    public function getNodeName(): string
    {
        return $this->node;
    }

    public function getMessage(): string
    {
        return sprintf('Skeleton bone index not found for node "%s".', $this->node);
    }

    public function getSeverity(): Severity
    {
        return Severity::Medium;
    }

    public function isEqual(InsightInterface $insight): bool
    {
        return $insight instanceof self && $insight->getNodeName() === $this->node;
    }
}
