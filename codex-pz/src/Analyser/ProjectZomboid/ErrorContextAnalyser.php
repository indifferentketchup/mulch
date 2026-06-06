<?php

namespace IndifferentKetchup\CodexPz\Analyser\ProjectZomboid;

use IndifferentKetchup\CodexPz\Analyser\Analyser;
use IndifferentKetchup\CodexPz\Analysis\Analysis;
use IndifferentKetchup\CodexPz\Analysis\AnalysisInterface;
use IndifferentKetchup\CodexPz\Analysis\ProjectZomboid\ErrorContextProblem;
use IndifferentKetchup\CodexPz\Analysis\ProjectZomboid\ErrorContextTruncatedInformation;
use IndifferentKetchup\CodexPz\Log\EntryInterface;
use IndifferentKetchup\CodexPz\Log\Level;

/**
 * Surfaces ERROR or WARNING entries with a sliding context window of
 * surrounding entries, so a viewer can see the lead-up and aftermath of
 * each event without scanning the full log. PatternAnalyser cannot
 * express this because windows span multiple entries; this walks once,
 * classifies by Level (already resolved by the parser), and emits one
 * ErrorContextProblem per hit.
 *
 * Stack-trace continuation lines are absorbed into the same Entry as the
 * level header that preceded them by PatternParser, so noise filtering
 * happens at parse time — windows here count Entries, not raw lines, and
 * a stack-trace ERROR contributes exactly one window.
 *
 * Overlapping windows are merged: when two error/warning entries fall
 * within CONTEXT_BEFORE + CONTEXT_AFTER of each other, the later
 * window's before- and after-ranges are clipped to start past the
 * previously emitted range so no Entry appears in two context arrays.
 * The hit cap is enforced after emission; reaching it adds an
 * ErrorContextTruncatedInformation to the analysis instead of further
 * problems.
 */
class ErrorContextAnalyser extends Analyser
{
    /**
     * Number of entries preceding a hit captured as leading context.
     * Twenty entries is wide enough to surface the immediate precursor
     * events (mod load, player join, prior warning) for a server-log
     * error without dragging in unrelated activity from minutes earlier.
     */
    public const int CONTEXT_BEFORE = 20;

    /**
     * Number of entries following a hit captured as trailing context.
     * Mirrors CONTEXT_BEFORE so windows are symmetric and the maximum
     * window size is CONTEXT_BEFORE + 1 (hit) + CONTEXT_AFTER = 41
     * entries.
     */
    public const int CONTEXT_AFTER = 20;

    /**
     * Maximum number of hits emitted before truncation. Caps memory and
     * output size on logs with cascading errors (e.g. a save-system
     * failure that produces an error every tick). Reaching the cap adds
     * an ErrorContextTruncatedInformation to the analysis so consumers
     * can flag truncation rather than silently dropping later hits.
     */
    public const int HIT_CAP = 500;

    public function analyse(): AnalysisInterface
    {
        $analysis = new Analysis();
        $analysis->setLog($this->log);

        $entries = [];
        foreach ($this->log as $entry) {
            $entries[] = $entry;
        }
        $count = count($entries);

        $hits = 0;
        $truncated = false;
        $lastEmittedIndex = -1;

        for ($i = 0; $i < $count; $i++) {
            $type = $this->classify($entries[$i]);
            if ($type === null) {
                continue;
            }

            if ($hits >= self::HIT_CAP) {
                $truncated = true;
                break;
            }

            $beforeStart = max($lastEmittedIndex + 1, $i - self::CONTEXT_BEFORE);
            if ($beforeStart > $i) {
                $beforeStart = $i;
            }
            $afterStart = max($lastEmittedIndex + 1, $i + 1);
            $afterEnd = min($count - 1, $i + self::CONTEXT_AFTER);
            $afterLength = max(0, $afterEnd - $afterStart + 1);

            $analysis->addInsight((new ErrorContextProblem())
                ->setEntry($entries[$i])
                ->setType($type)
                ->setEntryIndex($i + 1)
                ->setBefore(array_slice($entries, $beforeStart, $i - $beforeStart))
                ->setAfter(array_slice($entries, $afterStart, $afterLength)));

            $hits++;
            $lastEmittedIndex = max($lastEmittedIndex, $afterEnd);
        }

        if ($truncated) {
            $analysis->addInsight((new ErrorContextTruncatedInformation())
                ->setHitCap(self::HIT_CAP));
        }

        return $analysis;
    }

    /**
     * Classify an entry as 'error', 'warning', or null based on its Level.
     * Levels at or below ERROR (EMERGENCY/ALERT/CRITICAL/ERROR) collapse
     * into 'error'; WARNING alone collapses into 'warning'. Returns null
     * for anything less severe so the analyser skips it.
     */
    protected function classify(EntryInterface $entry): ?string
    {
        $level = $entry->getLevel()->asInt();
        if ($level <= Level::ERROR->asInt()) {
            return 'error';
        }
        if ($level === Level::WARNING->asInt()) {
            return 'warning';
        }
        return null;
    }
}
