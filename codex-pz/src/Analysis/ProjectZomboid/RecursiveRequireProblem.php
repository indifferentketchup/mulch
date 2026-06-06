<?php

namespace IndifferentKetchup\CodexPz\Analysis\ProjectZomboid;

use IndifferentKetchup\CodexPz\Analysis\InsightInterface;
use IndifferentKetchup\CodexPz\Analysis\PatternInsightInterface;
use IndifferentKetchup\CodexPz\Analysis\Problem;
use IndifferentKetchup\CodexPz\Analysis\Severity;
use IndifferentKetchup\CodexPz\Analysis\SeverityAwareInsightInterface;
use IndifferentKetchup\CodexPz\Pattern\ProjectZomboid\LuaWarningPattern;

class RecursiveRequireProblem extends Problem implements PatternInsightInterface, SeverityAwareInsightInterface
{
    private string $path = '';

    public static function getPatterns(): array
    {
        return [LuaWarningPattern::RECURSIVE_REQUIRE];
    }

    public function setMatches(array $matches, mixed $patternKey): void
    {
        $this->path = $matches['path'];
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getMessage(): string
    {
        return sprintf('Recursive require loop detected for "%s".', $this->path);
    }

    public function getSeverity(): Severity
    {
        return Severity::High;
    }

    public function isEqual(InsightInterface $insight): bool
    {
        return $insight instanceof self && $insight->getPath() === $this->path;
    }
}
