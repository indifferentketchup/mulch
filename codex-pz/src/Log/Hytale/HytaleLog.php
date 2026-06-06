<?php

namespace IndifferentKetchup\CodexPz\Log\Hytale;

use IndifferentKetchup\CodexPz\Detective\LinePatternDetector;
use IndifferentKetchup\CodexPz\Log\AnalysableLog;
use IndifferentKetchup\CodexPz\Log\DetectableLogInterface;

/**
 * Abstract base for all Hytale log types (server, client).
 *
 * Each concrete subclass overrides static $prefixPattern with the bracket-
 * or pipe-delimited PCRE prefix regex from src/Pattern/Hytale/. getPattern()
 * combines that prefix with an optional content sub-pattern to build a full
 * LINE regex anchored at the start of a line.
 *
 * Phase 1 port: only basic parser + line detection are wired. The upstream
 * version-extraction (HytaleVersionInformation) and JSON serialization with
 * version metadata are deferred to Phase 2 along with the Analyser ports.
 */
abstract class HytaleLog extends AnalysableLog implements DetectableLogInterface
{
    public static string $prefixPattern = '';

    /**
     * Build the line regex by combining $prefixPattern with an optional
     * content sub-pattern. Mirrors upstream Aternos\Codex\Hytale\Log\HytaleLog.
     *
     * @param string $contentPattern Optional content fragment appended after the prefix
     * @return string PCRE pattern anchored at line start
     */
    public static function getPattern(string $contentPattern = ''): string
    {
        if ($contentPattern) {
            $contentPattern = '\s*' . $contentPattern;
        }
        return '/^' . static::$prefixPattern . $contentPattern . '.*$/';
    }

    /**
     * Default detector for any Hytale log: a match-ratio detector against
     * the full prefix regex. Subclasses extend this with extra header
     * detectors (e.g. FirstLinesPatternDetector for the banner line).
     *
     * @return array
     */
    public static function getDetectors(): array
    {
        return [
            (new LinePatternDetector())->setPattern(static::getPattern()),
        ];
    }

    /**
     * @inheritDoc
     */
    public function getTitle(): string
    {
        return "Hytale " . $this->getTypeName() . " Log";
    }

    /**
     * Short type name for use in titles ("Server", "Client").
     */
    protected abstract function getTypeName(): string;
}
