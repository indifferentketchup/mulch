<?php

namespace IndifferentKetchup\CodexPz\Analyser\ProjectZomboid;

use IndifferentKetchup\CodexPz\Analyser\Analyser;
use IndifferentKetchup\CodexPz\Analysis\Analysis;
use IndifferentKetchup\CodexPz\Analysis\AnalysisInterface;
use IndifferentKetchup\CodexPz\Analysis\ProjectZomboid\ItemDuplicationProblem;
use IndifferentKetchup\CodexPz\Pattern\ProjectZomboid\ItemPattern;

/**
 * Flags suspicious item-gain frequency per (player, item) tuple. Slides a
 * fixed-second window across each group's events; a window with at least
 * THRESHOLD_COUNT positive-delta events triggers a problem.
 *
 * Negative-delta events (drops, transfers out) are ignored — they do not
 * indicate creation of items and a sufficiently fast trade-and-pickup loop
 * would self-cancel.
 *
 * Entry::getTime() resolves to integer Unix seconds, so sub-second
 * timestamps in the fixture all collapse to the same value. This is
 * acceptable for v1: events emitted within the same second are by
 * definition within any positive window.
 */
class ItemDuplicationAnalyser extends Analyser
{
    /**
     * Minimum number of same-item gain events that must fall inside the
     * window before a Problem is emitted. Five was picked because legitimate
     * gameplay rarely produces five identical items in ten seconds:
     * crafting has animation delays, looting is one-at-a-time, and zombie
     * drops are similarly serial. A burst of five suggests admin-spawn or
     * exploit. Tune downward if false negatives appear in production logs.
     */
    public const int THRESHOLD_COUNT = 5;

    /**
     * Length of the sliding window in seconds. Ten seconds covers a
     * realistic burst-loot scenario (e.g. crate of identical items) without
     * collapsing onto unrelated events. Combined with THRESHOLD_COUNT this
     * means an effective rate of 0.5 same-item events per second.
     */
    public const int THRESHOLD_WINDOW_SECONDS = 10;

    public function analyse(): AnalysisInterface
    {
        $analysis = new Analysis();
        $analysis->setLog($this->log);

        $groups = [];
        foreach ($this->log as $entry) {
            if (preg_match(ItemPattern::FIELDS, (string) $entry, $m) !== 1) {
                continue;
            }
            if (!str_starts_with($m['delta'], '+')) {
                continue;
            }
            $key = $m['steamid'] . '|' . $m['item'];
            $groups[$key][] = [
                'time' => $entry->getTime() ?? 0,
                'steamid' => $m['steamid'],
                'item' => $m['item'],
                'player' => $m['player'],
            ];
        }

        foreach ($groups as $events) {
            usort($events, static fn($a, $b) => $a['time'] <=> $b['time']);

            $left = 0;
            $eventCount = count($events);
            for ($right = 0; $right < $eventCount; $right++) {
                while ($events[$right]['time'] - $events[$left]['time'] > self::THRESHOLD_WINDOW_SECONDS) {
                    $left++;
                }
                if (($right - $left + 1) >= self::THRESHOLD_COUNT) {
                    $sample = $events[0];
                    $analysis->addInsight((new ItemDuplicationProblem())
                        ->setSteamId($sample['steamid'])
                        ->setPlayer($sample['player'])
                        ->setItem($sample['item'])
                        ->setEventCount($eventCount));
                    break;
                }
            }
        }

        return $analysis;
    }
}
