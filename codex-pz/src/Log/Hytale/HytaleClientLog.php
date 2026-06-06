<?php

namespace IndifferentKetchup\CodexPz\Log\Hytale;

use IndifferentKetchup\CodexPz\Analyser\AnalyserInterface;
use IndifferentKetchup\CodexPz\Analyser\PatternAnalyser;
use IndifferentKetchup\CodexPz\Detective\FirstLinesPatternDetector;
use IndifferentKetchup\CodexPz\Parser\ParserInterface;
use IndifferentKetchup\CodexPz\Parser\PatternParser;
use IndifferentKetchup\CodexPz\Pattern\Hytale\HytaleClientPattern;

class HytaleClientLog extends HytaleLog
{
    public static string $prefixPattern = HytaleClientPattern::PREFIX;
    protected static string $detectionPattern = 'HytaleClient.Application.Program|HytaleClient';

    public static function getDefaultParser(): ParserInterface
    {
        return (new PatternParser())
            ->setPattern(static::getPattern())
            ->setMatches([PatternParser::PREFIX, PatternParser::TIME, PatternParser::LEVEL])
            ->setTimeFormat("Y-m-d H:i:s");
    }

    /**
     * Phase 1 returns an empty PatternAnalyser stub — the upstream
     * HytaleClientAnalyser port lands in Phase 2.
     */
    public static function getDefaultAnalyser(): AnalyserInterface
    {
        return new PatternAnalyser();
    }

    public static function getDetectors(): array
    {
        return [
            (new FirstLinesPatternDetector())
                ->setPattern(static::getPattern(static::$detectionPattern))
                ->setWeight(0.95),
            ...parent::getDetectors(),
        ];
    }

    protected function getTypeName(): string
    {
        return "Client";
    }
}
