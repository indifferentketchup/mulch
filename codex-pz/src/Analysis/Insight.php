<?php

namespace IndifferentKetchup\CodexPz\Analysis;

use IndifferentKetchup\CodexPz\Log\EntryInterface;
use IndifferentKetchup\CodexPz\Log\LogInterface;

/**
 * Class Insight
 *
 * @package IndifferentKetchup\CodexPz\Analysis
 */
abstract class Insight implements InsightInterface
{
    protected ?AnalysisInterface $analysis = null;
    protected ?EntryInterface $entry = null;
    protected int $counter = 1;
    protected ?string $fingerprint = null;

    /**
     * Set the related entry
     *
     * @param EntryInterface $entry
     * @return $this
     */
    public function setEntry(EntryInterface $entry): static
    {
        $this->entry = $entry;
        return $this;
    }

    /**
     * Get the related entry
     *
     * @return EntryInterface|null
     */
    public function getEntry(): ?EntryInterface
    {
        return $this->entry;
    }

    /**
     * Increase the counter for this insight
     *
     * @return $this
     */
    public function increaseCounter(): static
    {
        $this->counter++;
        return $this;
    }

    /**
     * Get the current counter value
     *
     * @return int
     */
    public function getCounterValue(): int
    {
        return $this->counter;
    }

    /**
     * @return string
     */
    public function getFingerprint(): string
    {
        return $this->fingerprint ?? 'sha256:' . substr(hash('sha256', static::class), 0, 16);
    }

    /**
     * @param string $fp
     * @return $this
     */
    public function setFingerprint(string $fp): static
    {
        $this->fingerprint = $fp;
        return $this;
    }

    /**
     * @return string
     */
    public function __toString(): string
    {
        return $this->getMessage();
    }

    /**
     * @return array
     */
    public function jsonSerialize(): array
    {
        $base = [
            'message' => $this->getMessage(),
            'counter' => $this->getCounterValue(),
            'entry' => $this->getEntry(),
            'fingerprint' => $this->getFingerprint(),
        ];

        if ($this instanceof SeverityAwareInsightInterface) {
            $base['severity'] = $this->getSeverity()->value;
        }
        if ($this instanceof ModAttributedInsightInterface) {
            $base['mod'] = $this->getModAttribution();
        }
        if ($this instanceof EngineNoiseInsightInterface) {
            $base['engineNoise'] = true;
        }
        if ($this instanceof CauseChainInsightInterface) {
            $base['causeChain'] = $this->getCauseChain();
        }

        return $base;
    }

    /**
     * Set the related analysis
     *
     * @param AnalysisInterface $analysis
     * @return $this
     */
    public function setAnalysis(AnalysisInterface $analysis): static
    {
        $this->analysis = $analysis;
        return $this;
    }

    /**
     * Get the related analysis
     *
     * @return AnalysisInterface|null
     */
    public function getAnalysis(): ?AnalysisInterface
    {
        return $this->analysis;
    }

    /**
     * @return LogInterface|null
     */
    protected function getLog(): ?LogInterface
    {
        return $this->getAnalysis()?->getLog();
    }

    /**
     * @return string|null
     */
    protected function getLogContent(): ?string
    {
        return $this->getLog()?->getLogFile()?->getContent();
    }
}