<?php

namespace IndifferentKetchup\CodexPz\Analysis\ProjectZomboid;

use IndifferentKetchup\CodexPz\Analysis\CauseChainInsightInterface;
use IndifferentKetchup\CodexPz\Analysis\EngineNoiseInsightInterface;
use IndifferentKetchup\CodexPz\Analysis\Information;
use IndifferentKetchup\CodexPz\Analysis\InsightInterface;
use IndifferentKetchup\CodexPz\Analysis\Severity;
use IndifferentKetchup\CodexPz\Analysis\SeverityAwareInsightInterface;

/**
 * Known-benign engine chatter that happens to be "Exception thrown"-shaped:
 * DebugFileWatcher NoSuchFile registrations and Kahlua flushErrorMessage /
 * "dumping Lua stack trace" dumps. Emitted exclusively by
 * StackTraceClassificationAnalyser and tagged Severity::Noise so it cannot
 * outrank a real crash in a severity-weighted sort.
 *
 * Coalesces on a normalised signature (class + first frame, digits flattened)
 * so the same noise with varying paths collapses to one row.
 */
class EngineNoiseExceptionInformation extends Information implements
    EngineNoiseInsightInterface,
    SeverityAwareInsightInterface,
    CauseChainInsightInterface
{
    private string $exceptionClass = '';
    private string $signature = '';
    private ?string $causeChain = null;

    public function setExceptionClass(string $exceptionClass): static
    {
        $this->exceptionClass = $exceptionClass;
        $this->setLabel('Engine noise');
        $this->setValue($exceptionClass);
        return $this;
    }

    public function getExceptionClass(): string
    {
        return $this->exceptionClass;
    }

    public function setSignature(string $signature): static
    {
        $this->signature = $signature;
        return $this;
    }

    public function getSignature(): string
    {
        return $this->signature;
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
        return Severity::Noise;
    }

    public function isEqual(InsightInterface $insight): bool
    {
        return $insight instanceof self
            && $insight->getSignature() === $this->signature;
    }
}
