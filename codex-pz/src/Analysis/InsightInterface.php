<?php

namespace IndifferentKetchup\CodexPz\Analysis;

use IndifferentKetchup\CodexPz\Log\EntryInterface;
use JsonSerializable;

/**
 * Interface InsightInterface
 *
 * @package IndifferentKetchup\CodexPz\Analysis
 */
interface InsightInterface extends JsonSerializable
{
    /**
     * Get a human-readable message
     *
     * @return string
     */
    public function getMessage(): string;

    /**
     * @return string
     */
    public function __toString(): string;

    /**
     * Set the related entry
     *
     * @param EntryInterface $entry
     * @return $this
     */
    public function setEntry(EntryInterface $entry): static;

    /**
     * Get the related entry
     *
     * @return EntryInterface|null
     */
    public function getEntry(): ?EntryInterface;

    /**
     * Check if the $insight object is equal with the current object
     *
     * @param InsightInterface $insight
     * @return bool
     */
    public function isEqual(InsightInterface $insight): bool;

    /**
     * Increase the counter for this insight
     *
     * @return $this
     */
    public function increaseCounter(): static;

    /**
     * Get the current counter value
     *
     * @return int
     */
    public function getCounterValue(): int;

    /**
     * @return string
     */
    public function getFingerprint(): string;

    /**
     * Set the related analysis
     *
     * @param AnalysisInterface $analysis
     * @return $this
     */
    public function setAnalysis(AnalysisInterface $analysis): static;

    /**
     * Get the related analysis
     *
     * @return AnalysisInterface|null
     */
    public function getAnalysis(): ?AnalysisInterface;
}