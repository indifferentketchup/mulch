<?php

namespace IndifferentKetchup\CodexPz\Test\Tests\Games\ProjectZomboid\Analysis;

use IndifferentKetchup\CodexPz\Analysis\ProjectZomboid\ModLoadInformation;
use IndifferentKetchup\CodexPz\Pattern\ProjectZomboid\DebugServerPattern;
use PHPUnit\Framework\TestCase;

class ModLoadInformationTest extends TestCase
{
    public function testGetPatternsReturnsTheModLoadRegex(): void
    {
        $this->assertSame([DebugServerPattern::MOD_LOAD], ModLoadInformation::getPatterns());
    }

    public function testSetMatchesExtractsModName(): void
    {
        $line = '[16-04-26 00:01:19.131] LOG  : Mod          f:0, t:1776297679131, st:48,648,194,309> loading example_mod_alpha.';
        $this->assertSame(1, preg_match(DebugServerPattern::MOD_LOAD, $line, $matches));

        $insight = new ModLoadInformation();
        $insight->setMatches($matches, 0);

        $this->assertSame('Mod loaded', $insight->getLabel());
        $this->assertSame('example_mod_alpha', $insight->getValue());
    }

    public function testIsEqualCoalescesSameMod(): void
    {
        $a = $this->insightFor('example_mod_alpha');
        $b = $this->insightFor('example_mod_alpha');
        $c = $this->insightFor('example_mod_beta');

        $this->assertTrue($a->isEqual($b));
        $this->assertFalse($a->isEqual($c));
    }

    private function insightFor(string $modName): ModLoadInformation
    {
        $insight = new ModLoadInformation();
        $insight->setMatches(['mod' => $modName], 0);
        return $insight;
    }
}
