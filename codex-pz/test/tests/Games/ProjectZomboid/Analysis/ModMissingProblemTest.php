<?php

namespace IndifferentKetchup\CodexPz\Test\Tests\Games\ProjectZomboid\Analysis;

use IndifferentKetchup\CodexPz\Analysis\ProjectZomboid\ModMissingProblem;
use IndifferentKetchup\CodexPz\Analysis\ProjectZomboid\ModMissingSolution;
use IndifferentKetchup\CodexPz\Pattern\ProjectZomboid\DebugServerPattern;
use PHPUnit\Framework\TestCase;

class ModMissingProblemTest extends TestCase
{
    public function testGetPatternsReturnsTheModMissingRegex(): void
    {
        $this->assertSame([DebugServerPattern::MOD_MISSING], ModMissingProblem::getPatterns());
    }

    public function testSetMatchesExtractsModNameAndAttachesSolution(): void
    {
        $line = '[16-04-26 00:01:19.200] WARN : Mod          f:0, t:1776297679200, st:48,648,194,378> ZomboidFileSystem.loadModAndRequired> required mod "absent_mod" not found.';
        $this->assertSame(1, preg_match(DebugServerPattern::MOD_MISSING, $line, $matches));

        $problem = new ModMissingProblem();
        $problem->setMatches($matches, 0);

        $this->assertSame('absent_mod', $problem->getModName());
        $this->assertStringContainsString('absent_mod', $problem->getMessage());
        $this->assertCount(1, $problem->getSolutions());

        $solution = $problem->getSolutions()[0];
        $this->assertInstanceOf(ModMissingSolution::class, $solution);
        $this->assertStringContainsString('absent_mod', $solution->getMessage());
        $this->assertStringContainsString('serverconfig.ini', $solution->getMessage());
    }

    public function testIsEqualCoalescesSameMissingMod(): void
    {
        $a = $this->problemFor('mod_x');
        $b = $this->problemFor('mod_x');
        $c = $this->problemFor('mod_y');

        $this->assertTrue($a->isEqual($b));
        $this->assertFalse($a->isEqual($c));
    }

    private function problemFor(string $modName): ModMissingProblem
    {
        $problem = new ModMissingProblem();
        $problem->setMatches(['mod' => $modName], 0);
        return $problem;
    }
}
