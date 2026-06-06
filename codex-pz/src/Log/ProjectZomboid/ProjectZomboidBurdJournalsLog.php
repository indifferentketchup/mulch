<?php

namespace IndifferentKetchup\CodexPz\Log\ProjectZomboid;

use IndifferentKetchup\CodexPz\Analyser\AnalyserInterface;
use IndifferentKetchup\CodexPz\Analyser\PatternAnalyser;
use IndifferentKetchup\CodexPz\Detective\FilenameDetector;
use IndifferentKetchup\CodexPz\Detective\WeightedSinglePatternDetector;
use IndifferentKetchup\CodexPz\Parser\ParserInterface;
use IndifferentKetchup\CodexPz\Parser\PatternParser;
use IndifferentKetchup\CodexPz\Pattern\ProjectZomboid\BurdJournalsPattern;

class ProjectZomboidBurdJournalsLog extends ProjectZomboidEventLog
{
    public static function getDefaultParser(): ParserInterface
    {
        return static::makePatternParser(
            BurdJournalsPattern::LINE,
            [PatternParser::TIME, PatternParser::PREFIX, PatternParser::LEVEL]
        );
    }

    public static function getDefaultAnalyser(): AnalyserInterface
    {
        return new PatternAnalyser();
    }

    public static function getDetectors(): array
    {
        return [
            (new FilenameDetector())
                ->setPattern('/_BurdJournals\.txt$/')
                ->setWeight(0.95),
            (new WeightedSinglePatternDetector())
                ->setPattern('/\[BurdJournals\]/')
                ->setWeight(0.95),
        ];
    }

    public function getTitle(): string
    {
        return "Project Zomboid BurdJournals Mod Log";
    }
}
