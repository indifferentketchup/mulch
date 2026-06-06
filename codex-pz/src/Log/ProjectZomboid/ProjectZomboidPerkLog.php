<?php

namespace IndifferentKetchup\CodexPz\Log\ProjectZomboid;

use IndifferentKetchup\CodexPz\Analyser\AnalyserInterface;
use IndifferentKetchup\CodexPz\Analyser\ProjectZomboid\SkillProgressionAnomalyAnalyser;
use IndifferentKetchup\CodexPz\Detective\FilenameDetector;
use IndifferentKetchup\CodexPz\Detective\WeightedSinglePatternDetector;
use IndifferentKetchup\CodexPz\Parser\ParserInterface;
use IndifferentKetchup\CodexPz\Parser\PatternParser;
use IndifferentKetchup\CodexPz\Pattern\ProjectZomboid\PerkPattern;

class ProjectZomboidPerkLog extends ProjectZomboidEventLog
{
    public static function getDefaultParser(): ParserInterface
    {
        return static::makePatternParser(
            PerkPattern::LINE,
            [PatternParser::TIME]
        );
    }

    public static function getDefaultAnalyser(): AnalyserInterface
    {
        return new SkillProgressionAnomalyAnalyser();
    }

    public static function getDetectors(): array
    {
        return [
            (new FilenameDetector())
                ->setPattern('/_PerkLog\.txt$/')
                ->setWeight(0.95),
            (new WeightedSinglePatternDetector())
                ->setPattern('/\[Cooking=\d+, Fitness=\d+, Strength=\d+,/')
                ->setWeight(0.95),
        ];
    }

    public function getTitle(): string
    {
        return "Project Zomboid Perk Log";
    }
}
