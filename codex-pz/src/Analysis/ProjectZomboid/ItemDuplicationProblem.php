<?php

namespace IndifferentKetchup\CodexPz\Analysis\ProjectZomboid;

use IndifferentKetchup\CodexPz\Analysis\InsightInterface;
use IndifferentKetchup\CodexPz\Analysis\Problem;

/**
 * Problem emitted by ItemDuplicationAnalyser when a player gains the same
 * item code at a rate that exceeds the configured threshold. Coalesced by
 * the (Steam ID, item code) tuple so each suspicious group produces one
 * problem regardless of how many events fall inside the window.
 */
class ItemDuplicationProblem extends Problem
{
    private string $steamId = '';
    private string $player = '';
    private string $item = '';
    private int $eventCount = 0;

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

    public function setItem(string $item): static
    {
        $this->item = $item;
        return $this;
    }

    public function setEventCount(int $count): static
    {
        $this->eventCount = $count;
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

    public function getItem(): string
    {
        return $this->item;
    }

    public function getEventCount(): int
    {
        return $this->eventCount;
    }

    public function getMessage(): string
    {
        return sprintf(
            'Player %s (%s) gained %s %d times at a rate above the duplication threshold.',
            $this->player,
            $this->steamId,
            $this->item,
            $this->eventCount
        );
    }

    public function isEqual(InsightInterface $insight): bool
    {
        return $insight instanceof self
            && $insight->getSteamId() === $this->steamId
            && $insight->getItem() === $this->item;
    }
}
