<?php

namespace IndifferentKetchup\CodexPz\Analyser\ProjectZomboid;

use IndifferentKetchup\CodexPz\Analyser\PatternAnalyser;
use IndifferentKetchup\CodexPz\Log\EntryInterface;

/**
 * PatternAnalyser variant for the ProjectZomboid warning-family patterns.
 *
 * Enforces the one-producer seam (WARN-004/T2): entries containing
 * "Exception thrown" belong exclusively to StackTraceClassificationAnalyser,
 * so this analyser skips them. Every other entry is handled by the parent.
 */
class WarningPatternAnalyser extends PatternAnalyser
{
    protected function shouldAnalyseEntry(EntryInterface $entry): bool
    {
        return !str_contains((string) $entry, 'Exception thrown');
    }
}
