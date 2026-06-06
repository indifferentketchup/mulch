<?php

namespace IndifferentKetchup\CodexPz\Analysis\ProjectZomboid;

use IndifferentKetchup\CodexPz\Analysis\Information;
use IndifferentKetchup\CodexPz\Analysis\InsightInterface;

/**
 * Emitted by ErrorContextAnalyser exactly once when its hit cap is
 * reached, so downstream consumers can surface a "results truncated"
 * notice instead of silently dropping subsequent error/warning hits.
 */
class ErrorContextTruncatedInformation extends Information
{
    private int $hitCap = 0;

    /**
     * @param int $hitCap the cap that was hit (mirrors
     *     ErrorContextAnalyser::HIT_CAP at emission time)
     * @return $this
     */
    public function setHitCap(int $hitCap): static
    {
        $this->hitCap = $hitCap;
        $this->setLabel('Error context');
        $this->setValue(sprintf('truncated after %d hits', $hitCap));
        return $this;
    }

    /**
     * @return int
     */
    public function getHitCap(): int
    {
        return $this->hitCap;
    }

    public function isEqual(InsightInterface $insight): bool
    {
        return $insight instanceof self;
    }
}
