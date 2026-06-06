<?php

namespace IndifferentKetchup\CodexPz\Test\Src\Log;

use IndifferentKetchup\CodexPz\Analyser\PatternAnalyser;
use IndifferentKetchup\CodexPz\Log\AnalysableLog;
use IndifferentKetchup\CodexPz\Parser\PatternParser;
use IndifferentKetchup\CodexPz\Test\Src\Analysis\TestPatternInformation;
use IndifferentKetchup\CodexPz\Test\Src\Analysis\TestPatternProblem;

/**
 * Class TestLog
 */
class TestPatternLog extends AnalysableLog
{
    /**
     * Get the default parser
     *
     * @return PatternParser
     */
    public static function getDefaultParser(): PatternParser
    {
        return (new PatternParser())
            ->setPattern('/(\[([^\]]+)\] \[[^\/]+\/([^\]]+)\]).*/')
            ->setMatches([PatternParser::PREFIX, PatternParser::TIME, PatternParser::LEVEL])
            ->setTimeFormat('d.m.Y H:i:s');
    }

    /**
     * Get the default analyser
     *
     * @return PatternAnalyser
     */
    public static function getDefaultAnalyser(): PatternAnalyser
    {
        return (new PatternAnalyser())
            ->addPossibleInsightClass(TestPatternProblem::class)
            ->addPossibleInsightClass(TestPatternInformation::class);
    }
}