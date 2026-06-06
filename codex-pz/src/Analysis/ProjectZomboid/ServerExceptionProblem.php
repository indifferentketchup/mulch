<?php

namespace IndifferentKetchup\CodexPz\Analysis\ProjectZomboid;

use IndifferentKetchup\CodexPz\Analysis\InsightInterface;
use IndifferentKetchup\CodexPz\Analysis\PatternInsightInterface;
use IndifferentKetchup\CodexPz\Analysis\Problem;
use IndifferentKetchup\CodexPz\Pattern\ProjectZomboid\DebugServerPattern;

class ServerExceptionProblem extends Problem implements PatternInsightInterface
{
    private string $exceptionType = '';
    private string $body = '';

    public static function getPatterns(): array
    {
        return [DebugServerPattern::EXCEPTION];
    }

    public function setMatches(array $matches, mixed $patternKey): void
    {
        $this->exceptionType = $matches['type'];
        $this->body = trim($matches['body'] ?? '');
    }

    public function getExceptionType(): string
    {
        return $this->exceptionType;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    public function getMessage(): string
    {
        return sprintf('Exception thrown: %s', $this->exceptionType);
    }

    public function isEqual(InsightInterface $insight): bool
    {
        return $insight instanceof self
            && $insight->getExceptionType() === $this->exceptionType;
    }
}
