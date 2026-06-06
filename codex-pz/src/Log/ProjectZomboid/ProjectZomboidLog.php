<?php

namespace IndifferentKetchup\CodexPz\Log\ProjectZomboid;

use DateTimeZone;
use IndifferentKetchup\CodexPz\Log\AnalysableLog;
use IndifferentKetchup\CodexPz\Log\DetectableLogInterface;
use IndifferentKetchup\CodexPz\Parser\MultiPatternParser;
use IndifferentKetchup\CodexPz\Parser\PatternParser;

abstract class ProjectZomboidLog extends AnalysableLog implements DetectableLogInterface
{
    public const string TIME_FORMAT = 'd-m-y H:i:s.v';
    public const string DEFAULT_TIMEZONE = 'UTC';

    /**
     * Build a PatternParser preconfigured with the shared PZ time format
     * and timezone. Subclasses pass their line regex and the names of the
     * capture groups by index.
     *
     * @param string $pattern PCRE regex anchored at line start, with unnamed groups
     * @param array<int, string> $matches Match-type constants in capture-group order
     */
    protected static function makePatternParser(string $pattern, array $matches): PatternParser
    {
        return (new PatternParser())
            ->setPattern($pattern)
            ->setMatches($matches)
            ->setTimeFormat(static::TIME_FORMAT)
            ->setTimezone(new DateTimeZone(static::DEFAULT_TIMEZONE));
    }

    /**
     * Build a MultiPatternParser preconfigured with the shared PZ time format
     * and timezone. Each element of $formats is a [regex, matchTypes] pair.
     *
     * @param array<int, array{0: string, 1: array<int, string>}> $formats
     */
    protected static function makeMultiPatternParser(array $formats): MultiPatternParser
    {
        $parser = (new MultiPatternParser())
            ->setTimeFormat(static::TIME_FORMAT)
            ->setTimezone(new DateTimeZone(static::DEFAULT_TIMEZONE));

        foreach ($formats as [$regex, $matchTypes]) {
            $parser->addLineFormat($regex, $matchTypes);
        }

        return $parser;
    }
}
