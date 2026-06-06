<?php

namespace IndifferentKetchup\CodexPz\Analyser\ProjectZomboid;

use IndifferentKetchup\CodexPz\Analyser\Analyser;
use IndifferentKetchup\CodexPz\Analysis\Analysis;
use IndifferentKetchup\CodexPz\Analysis\AnalysisInterface;
use IndifferentKetchup\CodexPz\Analysis\ProjectZomboid\SkillProgressionAnomalyProblem;
use IndifferentKetchup\CodexPz\Pattern\ProjectZomboid\PerkPattern;

/**
 * Walks PerkLog entries, parses each perks-snapshot row into a
 * skill->level dict, and compares consecutive snapshots per Steam ID. If
 * any single skill gained more than THRESHOLD_DELTA levels between
 * snapshots, emits a SkillProgressionAnomalyProblem for that
 * (player, skill) pair.
 *
 * Login/Logout/LevelUp event rows are skipped — they have a single token
 * in the event field rather than a comma-separated list of Skill=N pairs.
 */
class SkillProgressionAnomalyAnalyser extends Analyser
{
    /**
     * Maximum plausible single-skill gain between two consecutive snapshots
     * of the same player. Project Zomboid skill leveling is slow: most
     * skills require thousands of XP per level, and even maxed grinding
     * setups don't routinely produce four-or-more level jumps in a single
     * session bridge. Set to 3 as a baseline; if production logs surface
     * frequent legitimate jumps of 4 (e.g. on heavily modded XP servers),
     * raise via subclass override or tune downward to catch finer abuse.
     */
    public const int THRESHOLD_DELTA = 3;

    public function analyse(): AnalysisInterface
    {
        $analysis = new Analysis();
        $analysis->setLog($this->log);

        $snapshots = [];
        foreach ($this->log as $entry) {
            $text = (string) $entry;
            if (preg_match(PerkPattern::FIELDS, $text, $m) !== 1) {
                continue;
            }
            if (preg_match(PerkPattern::PERK_PAIR, $m['event']) !== 1) {
                continue;
            }

            preg_match_all(PerkPattern::PERK_PAIR, $m['event'], $pairs, PREG_SET_ORDER);
            $skills = [];
            foreach ($pairs as $pair) {
                $skills[$pair['skill']] = (int) $pair['level'];
            }

            $snapshots[$m['steamid']][] = [
                'time' => $entry->getTime() ?? 0,
                'player' => $m['player'],
                'skills' => $skills,
            ];
        }

        foreach ($snapshots as $steamId => $playerSnapshots) {
            usort($playerSnapshots, static fn($a, $b) => $a['time'] <=> $b['time']);

            for ($i = 1; $i < count($playerSnapshots); $i++) {
                $prev = $playerSnapshots[$i - 1];
                $curr = $playerSnapshots[$i];

                foreach ($curr['skills'] as $skill => $currLevel) {
                    $prevLevel = $prev['skills'][$skill] ?? 0;
                    $delta = $currLevel - $prevLevel;
                    if ($delta > self::THRESHOLD_DELTA) {
                        $analysis->addInsight((new SkillProgressionAnomalyProblem())
                            ->setSteamId($steamId)
                            ->setPlayer($curr['player'])
                            ->setSkill($skill)
                            ->setFromLevel($prevLevel)
                            ->setToLevel($currLevel)
                            ->setDelta($delta));
                    }
                }
            }
        }

        return $analysis;
    }
}
