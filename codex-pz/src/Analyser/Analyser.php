<?php

namespace IndifferentKetchup\CodexPz\Analyser;

use IndifferentKetchup\CodexPz\Analysis\AnalysisInterface;
use IndifferentKetchup\CodexPz\Analysis\InsightInterface;
use IndifferentKetchup\CodexPz\Log\AnalysableLogInterface;

/**
 * Class Analyser
 *
 * @package IndifferentKetchup\CodexPz\Analyser
 */
abstract class Analyser implements AnalyserInterface
{
    protected ?AnalysableLogInterface $log = null;

    /**
     * Set the log
     *
     * @param AnalysableLogInterface $log
     * @return $this
     */
    public function setLog(AnalysableLogInterface $log): static
    {
        $this->log = $log;
        return $this;
    }

    public function postProcessAnalysis(AnalysisInterface $analysis): AnalysisInterface
    {
        return $analysis;
    }

    public function collectNoiseGates(AnalysisInterface $analysis): array
    {
        return [];
    }

    public function isInsightGated(InsightInterface $insight, AnalysisInterface $analysis): bool
    {
        return false;
    }
}
