<?php

namespace IndifferentKetchup\CodexPz\Log\Minecraft\Vanilla;

use IndifferentKetchup\CodexPz\Detective\FirstLinesPatternDetector;
use IndifferentKetchup\CodexPz\Log\Minecraft\MinecraftLog;
use IndifferentKetchup\CodexPz\Parser\ParserInterface;
use IndifferentKetchup\CodexPz\Parser\PatternParser;
use IndifferentKetchup\CodexPz\Pattern\Minecraft\VanillaServerPattern;

/**
 * Vanilla Minecraft server log.
 *
 * Skips the upstream abstract VanillaLog intermediate — Phase 1 ports
 * only one Vanilla variant (server), so the intermediate adds no factoring
 * value. Phase 2 reintroduces it when VanillaClientLog and the crash-report
 * variants land.
 */
class VanillaServerLog extends MinecraftLog
{
    public static string $linePattern = VanillaServerPattern::LINE;

    public static function getDefaultParser(): ParserInterface
    {
        return (new PatternParser())
            ->setPattern(VanillaServerPattern::LINE)
            ->setMatches([PatternParser::PREFIX, PatternParser::LEVEL]);
        // No setTimeFormat — Vanilla prefix `[HH:MM:SS]` is folded into PREFIX,
        // not separately captured. Matches upstream behaviour.
    }

    public static function getDetectors(): array
    {
        return [
            (new FirstLinesPatternDetector())
                ->setPattern(VanillaServerPattern::DETECTION_BANNER)
                ->setWeight(0.95),
            ...parent::getDetectors(),
        ];
    }

    protected function getVariantName(): string
    {
        return "Vanilla";
    }

    protected function getTypeName(): string
    {
        return "Server";
    }
}
