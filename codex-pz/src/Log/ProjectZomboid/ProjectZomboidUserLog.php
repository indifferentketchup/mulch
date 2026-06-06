<?php

namespace IndifferentKetchup\CodexPz\Log\ProjectZomboid;

use IndifferentKetchup\CodexPz\Analyser\AnalyserInterface;
use IndifferentKetchup\CodexPz\Analyser\ProjectZomboid\ConnectionFailureAnalyser;
use IndifferentKetchup\CodexPz\Detective\FilenameDetector;
use IndifferentKetchup\CodexPz\Detective\WeightedSinglePatternDetector;
use IndifferentKetchup\CodexPz\Parser\ParserInterface;
use IndifferentKetchup\CodexPz\Parser\PatternParser;
use IndifferentKetchup\CodexPz\Pattern\ProjectZomboid\UserPattern;

class ProjectZomboidUserLog extends ProjectZomboidEventLog
{
    public static function getDefaultParser(): ParserInterface
    {
        return static::makePatternParser(
            UserPattern::LINE,
            [PatternParser::TIME]
        );
    }

    public static function getDefaultAnalyser(): AnalyserInterface
    {
        return new ConnectionFailureAnalyser();
    }

    public static function getDetectors(): array
    {
        return [
            (new FilenameDetector())
                ->setPattern('/_user\.txt$/')
                ->setWeight(0.95),
            (new WeightedSinglePatternDetector())
                ->setPattern('/^\[[^\]]+\] Connection (?:add|disconnect) index=\d+ guid=\d+/m')
                ->setWeight(0.90),
            (new WeightedSinglePatternDetector())
                ->setPattern('/^\[[^\]]+\] \d{17} "[^"]+" (?:attempting to join|allowed to join)/m')
                ->setWeight(0.85),
        ];
    }

    public function getTitle(): string
    {
        return "Project Zomboid User Log";
    }
}
