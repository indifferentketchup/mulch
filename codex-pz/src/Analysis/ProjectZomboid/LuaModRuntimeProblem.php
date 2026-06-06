<?php

namespace IndifferentKetchup\CodexPz\Analysis\ProjectZomboid;

use IndifferentKetchup\CodexPz\Analysis\CauseChainInsightInterface;
use IndifferentKetchup\CodexPz\Analysis\InsightInterface;
use IndifferentKetchup\CodexPz\Analysis\ModAttributedInsightInterface;
use IndifferentKetchup\CodexPz\Analysis\ModAttribution;
use IndifferentKetchup\CodexPz\Analysis\Problem;
use IndifferentKetchup\CodexPz\Analysis\Severity;
use IndifferentKetchup\CodexPz\Analysis\SeverityAwareInsightInterface;

/**
 * A mod-attributed runtime crash: an "Exception thrown" entry whose assembled
 * stack carries a Lua((MOD:X)) marker (direct) or that was inferred from a
 * nearby mod marker. Emitted exclusively by StackTraceClassificationAnalyser.
 *
 * Coalesces on (exceptionClass, deepestModFrame) so the same crash in the same
 * mod method counts up rather than duplicating.
 */
class LuaModRuntimeProblem extends Problem implements
    SeverityAwareInsightInterface,
    ModAttributedInsightInterface,
    CauseChainInsightInterface
{
    private string $exceptionClass = '';
    private ?ModAttribution $modAttribution = null;
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

    public function setModAttribution(?ModAttribution $modAttribution): static
    {
        $this->modAttribution = $modAttribution;
        return $this;
    }

    public function getModAttribution(): ?ModAttribution
    {
        return $this->modAttribution;
    }

    public function getDeepestModFrame(): ?string
    {
        return $this->modAttribution?->deepestModFrame;
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
        return Severity::High;
    }

    public function getMessage(): string
    {
        $modName = $this->modAttribution?->modName ?? '';
        if ($modName !== '') {
            return sprintf('Mod "%s" runtime exception: %s', $modName, $this->exceptionClass);
        }
        return sprintf('Mod runtime exception: %s', $this->exceptionClass);
    }

    public function isEqual(InsightInterface $insight): bool
    {
        return $insight instanceof self
            && $insight->getExceptionClass() === $this->exceptionClass
            && $insight->getDeepestModFrame() === $this->getDeepestModFrame();
    }
}
