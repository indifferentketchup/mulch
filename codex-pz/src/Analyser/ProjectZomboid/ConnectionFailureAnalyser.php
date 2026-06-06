<?php

namespace IndifferentKetchup\CodexPz\Analyser\ProjectZomboid;

use IndifferentKetchup\CodexPz\Analyser\Analyser;
use IndifferentKetchup\CodexPz\Analysis\Analysis;
use IndifferentKetchup\CodexPz\Analysis\AnalysisInterface;
use IndifferentKetchup\CodexPz\Analysis\ProjectZomboid\ConnectionFailureProblem;
use IndifferentKetchup\CodexPz\Pattern\ProjectZomboid\UserPattern;

/**
 * Pairs "attempting to join" with subsequent "allowed to join" events per
 * Steam ID and flags any unmatched attempts. PatternAnalyser cannot express
 * this because it operates per-entry without cross-entry state, so this
 * walks the entire log once and aggregates before emitting Problems.
 *
 * "attempting to join used queue" is treated as an attempt; a player still
 * waiting in queue at end-of-log will therefore be flagged. This is
 * intentional v1 behaviour — a long-lived queue wait looks indistinguishable
 * from a real failure without timing context, and surfacing both lets a
 * human triage.
 */
class ConnectionFailureAnalyser extends Analyser
{
    public function analyse(): AnalysisInterface
    {
        $analysis = new Analysis();
        $analysis->setLog($this->log);

        $attempts = [];
        $allowed = [];
        $playerName = [];

        foreach ($this->log as $entry) {
            $text = (string) $entry;
            if (preg_match(UserPattern::PLAYER_EVENT, $text, $m) !== 1) {
                continue;
            }
            $steamId = $m['steamid'];
            $playerName[$steamId] = $m['player'];

            if (str_starts_with($m['event'], 'attempting to join')) {
                $attempts[$steamId] = ($attempts[$steamId] ?? 0) + 1;
            } elseif (str_starts_with($m['event'], 'allowed to join')) {
                $allowed[$steamId] = ($allowed[$steamId] ?? 0) + 1;
            }
        }

        foreach ($attempts as $steamId => $attemptCount) {
            $allowedCount = $allowed[$steamId] ?? 0;
            $unmatched = $attemptCount - $allowedCount;
            if ($unmatched <= 0) {
                continue;
            }

            $analysis->addInsight((new ConnectionFailureProblem())
                ->setSteamId($steamId)
                ->setPlayer($playerName[$steamId] ?? '')
                ->setUnmatchedAttempts($unmatched));
        }

        return $analysis;
    }
}
