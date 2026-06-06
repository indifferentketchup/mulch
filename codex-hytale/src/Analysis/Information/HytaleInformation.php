<?php

namespace Aternos\Codex\Hytale\Analysis\Information;

use Aternos\Codex\Analysis\Information;
use Aternos\Codex\Analysis\PatternInsightInterface;

abstract class HytaleInformation extends Information implements PatternInsightInterface
{
    /**
     * @inheritDoc
     */
    public function setMatches(array $matches, mixed $patternKey): void
    {
        $this->setValue($matches[4]);
    }
}