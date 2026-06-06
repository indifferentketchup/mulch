<?php

namespace IndifferentKetchup\CodexPz\Log\ProjectZomboid;

use IndifferentKetchup\CodexPz\Analyser\AnalyserInterface;
use IndifferentKetchup\CodexPz\Analyser\PatternAnalyser;
use IndifferentKetchup\CodexPz\Detective\FilenameDetector;
use IndifferentKetchup\CodexPz\Detective\WeightedSinglePatternDetector;
use IndifferentKetchup\CodexPz\Parser\ParserInterface;
use IndifferentKetchup\CodexPz\Parser\PatternParser;
use IndifferentKetchup\CodexPz\Pattern\ProjectZomboid\ClientActionPattern;

class ProjectZomboidClientActionLog extends ProjectZomboidEventLog
{
    public static function getDefaultParser(): ParserInterface
    {
        return static::makePatternParser(
            ClientActionPattern::LINE,
            [PatternParser::TIME]
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
                ->setPattern('/_ClientActionLog\.txt$/')
                ->setWeight(0.95),
            (new WeightedSinglePatternDetector())
                ->setPattern('/\[\d{17}\]\[(?:ISEnterVehicle|ISExitVehicle|ISWalkToTimedAction)\]\[/')
                ->setWeight(0.95),
        ];
    }

    public function getTitle(): string
    {
        return "Project Zomboid Client Action Log";
    }
}
