<?php

namespace IndifferentKetchup\CodexPz\Analysis\ProjectZomboid;

use IndifferentKetchup\CodexPz\Analysis\InsightInterface;
use IndifferentKetchup\CodexPz\Analysis\PatternInsightInterface;
use IndifferentKetchup\CodexPz\Analysis\Problem;
use IndifferentKetchup\CodexPz\Analysis\Severity;
use IndifferentKetchup\CodexPz\Analysis\SeverityAwareInsightInterface;
use IndifferentKetchup\CodexPz\Pattern\ProjectZomboid\LuaWarningPattern;

class FunctionMissingProblem extends Problem implements PatternInsightInterface, SeverityAwareInsightInterface
{
    private string $name = '';

    public static function getPatterns(): array
    {
        return [LuaWarningPattern::FUNCTION_MISSING];
    }

    public function setMatches(array $matches, mixed $patternKey): void
    {
        $this->name = $matches['name'];
    }

    public function getFunctionName(): string
    {
        return $this->name;
    }

    public function getMessage(): string
    {
        return sprintf('Lua function "%s" is not defined.', $this->name);
    }

    public function getSeverity(): Severity
    {
        return Severity::High;
    }

    public function isEqual(InsightInterface $insight): bool
    {
        return $insight instanceof self && $insight->getFunctionName() === $this->name;
    }
}
