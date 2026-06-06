<?php

namespace IndifferentKetchup\CodexPz\Log\ProjectZomboid;

use IndifferentKetchup\CodexPz\Analyser\AnalyserInterface;
use IndifferentKetchup\CodexPz\Analyser\PatternAnalyser;
use IndifferentKetchup\CodexPz\Analysis\ProjectZomboid\PvpDamageInformation;
use IndifferentKetchup\CodexPz\Detective\FilenameDetector;
use IndifferentKetchup\CodexPz\Detective\WeightedSinglePatternDetector;
use IndifferentKetchup\CodexPz\Parser\ParserInterface;
use IndifferentKetchup\CodexPz\Parser\PatternParser;
use IndifferentKetchup\CodexPz\Pattern\ProjectZomboid\PvpPattern;

class ProjectZomboidPvpLog extends ProjectZomboidEventLog
{
    public static function getDefaultParser(): ParserInterface
    {
        return static::makePatternParser(
            PvpPattern::LINE,
            [PatternParser::TIME, PatternParser::LEVEL, PatternParser::PREFIX]
        );
    }

    public static function getDefaultAnalyser(): AnalyserInterface
    {
        return (new PatternAnalyser())
            ->addPossibleInsightClass(PvpDamageInformation::class);
    }

    public static function getDetectors(): array
    {
        return [
            (new FilenameDetector())
                ->setPattern('/_pvp\.txt$/')
                ->setWeight(0.95),
            (new WeightedSinglePatternDetector())
                ->setPattern('/^\[[^\]]+\]\[\w+\] Combat: "[^"]+" \(/m')
                ->setWeight(0.95),
            (new WeightedSinglePatternDetector())
                ->setPattern('/^\[[^\]]+\]\[\w+\] Safety: "/m')
                ->setWeight(0.85),
        ];
    }

    public function getTitle(): string
    {
        return "Project Zomboid PvP Log";
    }
}
