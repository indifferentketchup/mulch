<?php

namespace IndifferentKetchup\CodexPz\Analysis\ProjectZomboid;

use IndifferentKetchup\CodexPz\Analysis\InsightInterface;
use IndifferentKetchup\CodexPz\Analysis\Problem;
use IndifferentKetchup\CodexPz\Log\EntryInterface;

/**
 * Problem emitted by ErrorContextAnalyser for each ERROR or WARNING entry,
 * carrying a sliding window of surrounding entries as before/after
 * context. Coalesced by 1-based entryIndex so re-adding the same hit
 * never produces duplicate problems.
 */
class ErrorContextProblem extends Problem
{
    private string $type = 'error';
    private int $entryIndex = 0;

    /**
     * @var EntryInterface[]
     */
    private array $before = [];

    /**
     * @var EntryInterface[]
     */
    private array $after = [];

    /**
     * @param string $type 'error' or 'warning'
     * @return $this
     */
    public function setType(string $type): static
    {
        $this->type = $type;
        return $this;
    }

    /**
     * @return string
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * @param int $entryIndex 1-based index of the hit entry within the log
     * @return $this
     */
    public function setEntryIndex(int $entryIndex): static
    {
        $this->entryIndex = $entryIndex;
        return $this;
    }

    /**
     * @return int 1-based index of the hit entry within the log
     */
    public function getEntryIndex(): int
    {
        return $this->entryIndex;
    }

    /**
     * @param EntryInterface[] $entries
     * @return $this
     */
    public function setBefore(array $entries): static
    {
        $this->before = $entries;
        return $this;
    }

    /**
     * @return EntryInterface[]
     */
    public function getBefore(): array
    {
        return $this->before;
    }

    /**
     * @param EntryInterface[] $entries
     * @return $this
     */
    public function setAfter(array $entries): static
    {
        $this->after = $entries;
        return $this;
    }

    /**
     * @return EntryInterface[]
     */
    public function getAfter(): array
    {
        return $this->after;
    }

    /**
     * Convenience accessor returning before-context, hit entry, and
     * after-context as a single ordered array of at most
     * ErrorContextAnalyser::CONTEXT_BEFORE + 1 + CONTEXT_AFTER = 41
     * entries.
     *
     * @return EntryInterface[]
     */
    public function getContext(): array
    {
        return [...$this->before, $this->getEntry(), ...$this->after];
    }

    public function getMessage(): string
    {
        return sprintf(
            '%s at entry %d (%d before, %d after)',
            strtoupper($this->type),
            $this->entryIndex,
            count($this->before),
            count($this->after)
        );
    }

    public function isEqual(InsightInterface $insight): bool
    {
        return $insight instanceof self && $insight->getEntryIndex() === $this->entryIndex;
    }
}
