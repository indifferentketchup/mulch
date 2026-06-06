<?php

namespace IndifferentKetchup\CodexPz\Log\ProjectZomboid;

use IndifferentKetchup\CodexPz\Analyser\AnalyserInterface;
use IndifferentKetchup\CodexPz\Analyser\ProjectZomboid\ItemDuplicationAnalyser;
use IndifferentKetchup\CodexPz\Detective\FilenameDetector;
use IndifferentKetchup\CodexPz\Detective\WeightedSinglePatternDetector;
use IndifferentKetchup\CodexPz\Parser\ParserInterface;
use IndifferentKetchup\CodexPz\Parser\PatternParser;
use IndifferentKetchup\CodexPz\Pattern\ProjectZomboid\ItemPattern;

class ProjectZomboidItemLog extends ProjectZomboidEventLog
{
    public static function getDefaultParser(): ParserInterface
    {
        return static::makePatternParser(
            ItemPattern::LINE,
            [PatternParser::TIME]
        );
    }

    public static function getDefaultAnalyser(): AnalyserInterface
    {
        return new ItemDuplicationAnalyser();
    }

    public static function getDetectors(): array
    {
        return [
            (new FilenameDetector())
                ->setPattern('/_item\.txt$/')
                ->setWeight(0.95),
            (new WeightedSinglePatternDetector())
                ->setPattern('/^\[[^\]]+\] \d{17} "[^"]+" (?:container|floor|inventory) [+\-]\d+ /m')
                ->setWeight(0.90),
        ];
    }

    public function getTitle(): string
    {
        return "Project Zomboid Item Log";
    }
}
