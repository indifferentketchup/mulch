<?php

namespace IndifferentKetchup\CodexPz\Analysis\ProjectZomboid;

use IndifferentKetchup\CodexPz\Analysis\InsightInterface;
use IndifferentKetchup\CodexPz\Analysis\PatternInsightInterface;
use IndifferentKetchup\CodexPz\Analysis\Problem;
use IndifferentKetchup\CodexPz\Pattern\ProjectZomboid\DebugServerPattern;

class ModMissingProblem extends Problem implements PatternInsightInterface
{
    private string $modName = '';

    public static function getPatterns(): array
    {
        return [DebugServerPattern::MOD_MISSING];
    }

    public function setMatches(array $matches, mixed $patternKey): void
    {
        $this->modName = $matches['mod'];
        $this->addSolution((new ModMissingSolution())->setModName($this->modName));
    }

    public function getModName(): string
    {
        return $this->modName;
    }

    public function getMessage(): string
    {
        return sprintf('Required mod "%s" not found.', $this->modName);
    }

    public function isEqual(InsightInterface $insight): bool
    {
        return $insight instanceof self && $insight->getModName() === $this->modName;
    }
}
