<?php

namespace IndifferentKetchup\CodexPz\Log;

use IndifferentKetchup\CodexPz\Analyser\AnalyserInterface;
use IndifferentKetchup\CodexPz\Analysis\AnalysisInterface;

/**
 * Interface AnalysableLogInterface
 *
 * @package IndifferentKetchup\CodexPz\Log
 */
interface AnalysableLogInterface
{
    /**
     * Get the default analyser
     *
     * @return AnalyserInterface
     */
    public static function getDefaultAnalyser(): AnalyserInterface;

    /**
     * Analyse a  log file with an analyser
     *
     * Every log type should have a default analyser,
     * but the $analyser argument can be used to override
     * the default analyser
     *
     * @param AnalyserInterface|null $analyser
     * @return AnalysisInterface
     */
    public function analyse(?AnalyserInterface $analyser = null): AnalysisInterface;
}