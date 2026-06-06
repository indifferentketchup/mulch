<?php

namespace IndifferentKetchup\CodexPz\Test\Tests\Games\ProjectZomboid\Analysis;

use IndifferentKetchup\CodexPz\Analysis\ProjectZomboid\PvpDamageInformation;
use IndifferentKetchup\CodexPz\Pattern\ProjectZomboid\PvpPattern;
use PHPUnit\Framework\TestCase;

class PvpDamageInformationTest extends TestCase
{
    public function testGetPatternsReturnsCombatRealRegex(): void
    {
        $this->assertSame([PvpPattern::COMBAT_REAL], PvpDamageInformation::getPatterns());
    }

    public function testCombatRealMatchesPositiveDamageRealWeapon(): void
    {
        $line = 'Combat: "Player1" (1005,2005,0) hit "Player2" (1006,2005,0) weapon="Tire Iron (Worn)" damage=0.112317.';
        $this->assertSame(1, preg_match(PvpPattern::COMBAT_REAL, $line, $m));

        $insight = new PvpDamageInformation();
        $insight->setMatches($m, 0);

        $this->assertSame('PvP combat', $insight->getLabel());
        $this->assertSame('Player1 hit Player2 with Tire Iron (Worn)', $insight->getValue());
    }

    public function testCombatRealRejectsZombieWeapon(): void
    {
        $line = 'Combat: "Player1" (1005,2005,0) hit "Player1" (1005,2005,0) weapon="zombie" damage=-1.000000.';
        $this->assertSame(0, preg_match(PvpPattern::COMBAT_REAL, $line));
    }

    public function testCombatRealRejectsZeroDamage(): void
    {
        $line = 'Combat: "Player1" (1100,2200,0) hit "Player2" (1100,2201,0) weapon="vehicle" damage=0.000000.';
        $this->assertSame(0, preg_match(PvpPattern::COMBAT_REAL, $line));
    }

    public function testCombatRealRejectsNegativeDamage(): void
    {
        $line = 'Combat: "Player1" (1005,2005,0) hit "Player2" (1005,2005,0) weapon="Bare Hands" damage=-0.500000.';
        $this->assertSame(0, preg_match(PvpPattern::COMBAT_REAL, $line));
    }

    public function testIsEqualCoalescesSameAttackerVictimWeapon(): void
    {
        $a = $this->insightFor('Player1', 'Player2', 'Bare Hands');
        $b = $this->insightFor('Player1', 'Player2', 'Bare Hands');
        $c = $this->insightFor('Player1', 'Player2', 'Tire Iron');

        $this->assertTrue($a->isEqual($b));
        $this->assertFalse($a->isEqual($c));
    }

    private function insightFor(string $attacker, string $victim, string $weapon): PvpDamageInformation
    {
        $insight = new PvpDamageInformation();
        $insight->setMatches([
            'attacker' => $attacker,
            'victim' => $victim,
            'weapon' => $weapon,
        ], 0);
        return $insight;
    }
}
