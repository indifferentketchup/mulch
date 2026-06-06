<?php

namespace IndifferentKetchup\CodexPz\Log\ProjectZomboid;

use IndifferentKetchup\CodexPz\Analyser\AnalyserInterface;
use IndifferentKetchup\CodexPz\Analyser\PatternAnalyser;
use IndifferentKetchup\CodexPz\Analysis\ProjectZomboid\AdminAddedItemInformation;
use IndifferentKetchup\CodexPz\Analysis\ProjectZomboid\AdminAddedXpInformation;
use IndifferentKetchup\CodexPz\Analysis\ProjectZomboid\AdminChangedOptionInformation;
use IndifferentKetchup\CodexPz\Analysis\ProjectZomboid\AdminGrantedAccessInformation;
use IndifferentKetchup\CodexPz\Analysis\ProjectZomboid\AdminReloadedOptionsInformation;
use IndifferentKetchup\CodexPz\Analysis\ProjectZomboid\AdminTeleportedInformation;
use IndifferentKetchup\CodexPz\Detective\FilenameDetector;
use IndifferentKetchup\CodexPz\Detective\WeightedSinglePatternDetector;
use IndifferentKetchup\CodexPz\Parser\ParserInterface;
use IndifferentKetchup\CodexPz\Parser\PatternParser;
use IndifferentKetchup\CodexPz\Pattern\ProjectZomboid\AdminPattern;

class ProjectZomboidAdminLog extends ProjectZomboidEventLog
{
    public static function getDefaultParser(): ParserInterface
    {
        return static::makePatternParser(
            AdminPattern::LINE,
            [PatternParser::TIME]
        );
    }

    public static function getDefaultAnalyser(): AnalyserInterface
    {
        return (new PatternAnalyser())
            ->addPossibleInsightClass(AdminAddedItemInformation::class)
            ->addPossibleInsightClass(AdminAddedXpInformation::class)
            ->addPossibleInsightClass(AdminGrantedAccessInformation::class)
            ->addPossibleInsightClass(AdminChangedOptionInformation::class)
            ->addPossibleInsightClass(AdminReloadedOptionsInformation::class)
            ->addPossibleInsightClass(AdminTeleportedInformation::class);
    }

    public static function getDetectors(): array
    {
        return [
            (new FilenameDetector())
                ->setPattern('/_admin\.txt$/')
                ->setWeight(0.95),
            (new WeightedSinglePatternDetector())
                ->setPattern('/^\[[^\]]+\] .+? added item Base\.\S+ in .+?\'s inventory/m')
                ->setWeight(0.90),
            (new WeightedSinglePatternDetector())
                ->setPattern('/^\[[^\]]+\] .+? granted (?:admin|user|moderator|gm|observer) access level on /m')
                ->setWeight(0.85),
        ];
    }

    public function getTitle(): string
    {
        return "Project Zomboid Admin Log";
    }
}
