<?php

namespace IndifferentKetchup\CodexPz\Analysis\ProjectZomboid;

use IndifferentKetchup\CodexPz\Analysis\InsightInterface;
use IndifferentKetchup\CodexPz\Analysis\Problem;

/**
 * Problem emitted by SkillProgressionAnomalyAnalyser when a single skill
 * gained more than the configured threshold between two consecutive
 * snapshots of the same player. Coalesced by (Steam ID, skill).
 */
class SkillProgressionAnomalyProblem extends Problem
{
    private string $steamId = '';
    private string $player = '';
    private string $skill = '';
    private int $fromLevel = 0;
    private int $toLevel = 0;
    private int $delta = 0;

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

    public function setSkill(string $skill): static
    {
        $this->skill = $skill;
        return $this;
    }

    public function setFromLevel(int $level): static
    {
        $this->fromLevel = $level;
        return $this;
    }

    public function setToLevel(int $level): static
    {
        $this->toLevel = $level;
        return $this;
    }

    public function setDelta(int $delta): static
    {
        $this->delta = $delta;
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

    public function getSkill(): string
    {
        return $this->skill;
    }

    public function getFromLevel(): int
    {
        return $this->fromLevel;
    }

    public function getToLevel(): int
    {
        return $this->toLevel;
    }

    public function getDelta(): int
    {
        return $this->delta;
    }

    public function getMessage(): string
    {
        return sprintf(
            'Player %s (%s) gained %d levels of %s between snapshots (%d to %d).',
            $this->player,
            $this->steamId,
            $this->delta,
            $this->skill,
            $this->fromLevel,
            $this->toLevel
        );
    }

    public function isEqual(InsightInterface $insight): bool
    {
        return $insight instanceof self
            && $insight->getSteamId() === $this->steamId
            && $insight->getSkill() === $this->skill;
    }
}
