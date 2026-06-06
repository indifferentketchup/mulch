<?php

namespace IndifferentKetchup\CodexPz\Log\ProjectZomboid;

use IndifferentKetchup\CodexPz\Analyser\AnalyserInterface;
use IndifferentKetchup\CodexPz\Analyser\PatternAnalyser;
use IndifferentKetchup\CodexPz\Detective\FilenameDetector;
use IndifferentKetchup\CodexPz\Detective\WeightedSinglePatternDetector;
use IndifferentKetchup\CodexPz\Parser\ParserInterface;
use IndifferentKetchup\CodexPz\Parser\PatternParser;
use IndifferentKetchup\CodexPz\Pattern\ProjectZomboid\ChatPattern;

class ProjectZomboidChatLog extends ProjectZomboidEventLog
{
    public static function getDefaultParser(): ParserInterface
    {
        return static::makePatternParser(
            ChatPattern::LINE,
            [PatternParser::TIME, PatternParser::LEVEL]
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
                ->setPattern('/_chat\.txt$/')
                ->setWeight(0.95),
            (new WeightedSinglePatternDetector())
                ->setPattern('/Got message:ChatMessage\{chat=\w+/')
                ->setWeight(0.95),
            (new WeightedSinglePatternDetector())
                ->setPattern('/Start chat server initialization/')
                ->setWeight(0.85),
        ];
    }

    public function getTitle(): string
    {
        return "Project Zomboid Chat Log";
    }
}
