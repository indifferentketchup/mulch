<?php

namespace IndifferentKetchup\CodexPz\Analysis\ProjectZomboid;

use IndifferentKetchup\CodexPz\Analysis\InsightInterface;
use IndifferentKetchup\CodexPz\Analysis\Problem;

/**
 * Problem emitted by ConnectionFailureAnalyser when a player's
 * "attempting to join" event count exceeds their "allowed to join" count
 * within the same log file. Coalesced by Steam ID so each player produces
 * at most one problem regardless of how many unmatched attempts they have.
 */
class ConnectionFailureProblem extends Problem
{
    private string $steamId = '';
    private string $player = '';
    private int $unmatchedAttempts = 0;

    public function setSteamId(string $steamId): static
    {
        $this->steamId = $steamId;
        return $this;
    }

    public function setPlayer(string $player): static
    {
        $this->player = $player;
        return $this;
    }

    public function setUnmatchedAttempts(int $count): static
    {
        $this->unmatchedAttempts = $count;
        return $this;
    }

    public function getSteamId(): string
    {
        return $this->steamId;
    }

    public function getPlayer(): string
    {
        return $this->player;
    }

    public function getUnmatchedAttempts(): int
    {
        return $this->unmatchedAttempts;
    }

    public function getMessage(): string
    {
        return sprintf(
            'Player %s (%s) had %d "attempting to join" event(s) without a matching "allowed to join".',
            $this->player,
            $this->steamId,
            $this->unmatchedAttempts
        );
    }

    public function isEqual(InsightInterface $insight): bool
    {
        return $insight instanceof self && $insight->getSteamId() === $this->steamId;
    }
}
