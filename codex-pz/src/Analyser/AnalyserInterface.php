<?php

namespace IndifferentKetchup\CodexPz\Analyser;

use IndifferentKetchup\CodexPz\Analysis\AnalysisInterface;
use IndifferentKetchup\CodexPz\Analysis\InsightInterface;
use IndifferentKetchup\CodexPz\Log\AnalysableLogInterface;

/**
 * Interface AnalyserInterface
 *
 * @package IndifferentKetchup\CodexPz\Analyser
 */
interface AnalyserInterface
{
    /**
     * Set the log
     *
     * @param AnalysableLogInterface $log
     * @return $this
     */
    public function setLog(AnalysableLogInterface $log): static;

    /**
     * Analyse a log and return an Analysis
     *
     * @return AnalysisInterface
     */
    public function analyse(): AnalysisInterface;

    public function postProcessAnalysis(AnalysisInterface $analysis): AnalysisInterface;

    public function collectNoiseGates(AnalysisInterface $analysis): array;

    public function isInsightGated(InsightInterface $insight, AnalysisInterface $analysis): bool;
}
