<?php

namespace IndifferentKetchup\CodexPz\Analysis\ProjectZomboid;

use IndifferentKetchup\CodexPz\Analysis\CauseChainInsightInterface;
use IndifferentKetchup\CodexPz\Analysis\InsightInterface;
use IndifferentKetchup\CodexPz\Analysis\Problem;
use IndifferentKetchup\CodexPz\Analysis\Severity;
use IndifferentKetchup\CodexPz\Analysis\SeverityAwareInsightInterface;

/**
 * A non-mod-attributed exception: an "Exception thrown" entry with no Lua mod
 * marker and no engine-noise signature. Covers generic Java exceptions and the
 * IsoPropertyType$IsoPropertyTypeNotFoundException property-not-found family.
 * Emitted exclusively by StackTraceClassificationAnalyser.
 *
 * Coalesces on (exceptionClass, file:line).
 */
class JavaExceptionProblem extends Problem implements
    SeverityAwareInsightInterface,
    CauseChainInsightInterface
{
    private string $exceptionClass = '';
    private string $fileLine = '';
    private ?string $causeChain = null;

    public function setExceptionClass(string $exceptionClass): static
    {
        $this->exceptionClass = $exceptionClass;
        return $this;
    }

    public function getExceptionClass(): string
    {
        return $this->exceptionClass;
    }

    public function setFileLine(string $fileLine): static
    {
        $this->fileLine = $fileLine;
        return $this;
    }

    public function getFileLine(): string
    {
        return $this->fileLine;
    }

    public function setCauseChain(?string $causeChain): static
    {
        $this->causeChain = $causeChain;
        return $this;
    }

    public function getCauseChain(): ?string
    {
        return $this->causeChain;
    }

    public function getSeverity(): Severity
    {
        return Severity::Medium;
    }

    public function getMessage(): string
    {
        return sprintf('Exception thrown: %s', $this->exceptionClass);
    }

    public function isEqual(InsightInterface $insight): bool
    {
        return $insight instanceof self
            && $insight->getExceptionClass() === $this->exceptionClass
            && $insight->getFileLine() === $this->fileLine;
    }
}
