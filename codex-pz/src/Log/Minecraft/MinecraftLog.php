<?php

namespace IndifferentKetchup\CodexPz\Log\Minecraft;

use IndifferentKetchup\CodexPz\Analyser\AnalyserInterface;
use IndifferentKetchup\CodexPz\Analyser\PatternAnalyser;
use IndifferentKetchup\CodexPz\Detective\LinePatternDetector;
use IndifferentKetchup\CodexPz\Log\AnalysableLog;
use IndifferentKetchup\CodexPz\Log\DetectableLogInterface;

/**
 * Abstract base for all Minecraft log types.
 *
 * Phase 1 ports only the Vanilla server variant. The upstream package has
 * 30+ subclasses (Fabric, Forge, NeoForge, Quilt, Bukkit/Spigot/Paper/Purpur/
 * Folia, Mohist/Magma/Arclight, BungeeCord/Velocity/Geyser/Waterfall, Bedrock,
 * Pocketmine, plus crash reports + launcher client logs); those land in Phase 2.
 *
 * The upstream MinecraftLog carries Translator-driven type-name lookups,
 * version extraction via getDefaultAnalyser, getId/getName/getVersion/
 * jsonSerialize overrides, and LogType marker interfaces. Phase 1 strips all
 * of that; Phase 2 will reintroduce them alongside the rest of the variants.
 */
abstract class MinecraftLog extends AnalysableLog implements DetectableLogInterface
{
    /**
     * PCRE LINE pattern; concrete subclasses MUST override with their variant's
     * regex. The default `'//'` is intentionally a sentinel — getDetectors()
     * throws if it's still in place at detection time.
     */
    public static string $linePattern = '//';

    /**
     * Phase 1 returns an empty PatternAnalyser stub — the upstream
     * variant Analysers (VanillaAnalyser, etc.) land in Phase 2.
     */
    public static function getDefaultAnalyser(): AnalyserInterface
    {
        return new PatternAnalyser();
    }

    /**
     * Default detector for any Minecraft log: a match-ratio detector against
     * the full LINE regex. Subclasses extend this with extra header detectors
     * (e.g. FirstLinesPatternDetector for the "Starting minecraft server
     * version ..." banner).
     *
     * @return array
     */
    public static function getDetectors(): array
    {
        if (static::$linePattern === '//') {
            throw new \LogicException(
                static::class . ' must override static $linePattern with a real LINE regex.'
            );
        }

        return [
            (new LinePatternDetector())->setPattern(static::$linePattern),
        ];
    }

    /**
     * @inheritDoc
     */
    public function getTitle(): string
    {
        return "Minecraft " . $this->getVariantName() . " " . $this->getTypeName() . " Log";
    }

    /**
     * Short variant name for use in titles ("Vanilla", "Fabric", ...).
     */
    protected abstract function getVariantName(): string;

    /**
     * Short type name for use in titles ("Server", "Client", ...).
     */
    protected abstract function getTypeName(): string;
}
