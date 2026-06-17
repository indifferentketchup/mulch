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
    protected Kind $kind = Kind::Unknown;
    protected Attribution $attribution = Attribution::Unattributed;
    protected ?int $rank = null;
    protected ?bool $gated = null;

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
            'kind' => $this->getKind()->value,
            'attribution' => $this->getAttribution()->value,
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

        $base['rank'] = $this->getRankScore();
        $base['gated'] = $this->isGated();

        return $base;
    }

    /**
     * The computed priority score this insight sorts by (higher = surface first).
     */
    public function getRankScore(): int
    {
        if ($this->rank !== null) {
            return $this->rank;
        }

        return RankCalculator::applyLlmAdjustment(
            RankCalculator::compute($this),
            $this->getAnalysis()?->getLlmVerdict(),
        );
    }

    public function setRank(?int $rank): static
    {
        $this->rank = $rank;
        return $this;
    }

    /**
     * Whether this insight is engine noise routed to the collapsed footer. An
     * explicit override (set via setGated) wins; otherwise gate engine-noise
     * insights and high-volume unattributed low-severity chatter.
     */
    public function isGated(): bool
    {
        return $this->gated ?? false;
    }

    /**
     * Force the gate state (analysis pass override). Pass null to fall back to
     * the default predicate.
     */
    public function setGated(?bool $gated): static
    {
        $this->gated = $gated;
        return $this;
    }

    public function getKind(): Kind
    {
        return $this->kind;
    }

    public function setKind(Kind $kind): static
    {
        $this->kind = $kind;
        return $this;
    }

    public function getAttribution(): Attribution
    {
        return $this->attribution;
    }

    public function setAttribution(Attribution $attribution): static
    {
        $this->attribution = $attribution;
        return $this;
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
