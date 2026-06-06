<?php

namespace IndifferentKetchup\CodexPz\Test\Tests\Games\ProjectZomboid\Analysis;

use IndifferentKetchup\CodexPz\Analysis\ProjectZomboid\FunctionMissingProblem;
use IndifferentKetchup\CodexPz\Analysis\ProjectZomboid\RecursiveRequireProblem;
use IndifferentKetchup\CodexPz\Analysis\ProjectZomboid\RequireFailedProblem;
use IndifferentKetchup\CodexPz\Analysis\Severity;
use IndifferentKetchup\CodexPz\Pattern\ProjectZomboid\LuaWarningPattern;
use PHPUnit\Framework\TestCase;

class LuaWarningProblemTest extends TestCase
{
    // ── RequireFailedProblem ──────────────────────────────────────────────────

    public function testRequireFailedPatternMatchesRealShape(): void
    {
        $line = '[16-04-26 12:34:56.789] WARN : Lua          f:0, t:1234567890123, st:1,2,3,4> at Lua(Vanilla).corpseStorageCheck.lua> require("ISUI/ISInventoryPaneContextMenu") failed.';
        $this->assertSame(1, preg_match(LuaWarningPattern::REQUIRE_FAILED, $line, $m));
        $this->assertSame('ISUI/ISInventoryPaneContextMenu', $m['path']);
    }

    public function testRequireFailedExtractsPath(): void
    {
        $problem = new RequireFailedProblem();
        $problem->setMatches(['path' => 'ISUI/ISVehicleMenu'], 0);

        $this->assertSame('ISUI/ISVehicleMenu', $problem->getPath());
        $this->assertStringContainsString('ISUI/ISVehicleMenu', $problem->getMessage());
        $this->assertSame(Severity::Medium, $problem->getSeverity());
    }

    public function testRequireFailedCoalescesBySamePath(): void
    {
        $a = new RequireFailedProblem();
        $a->setMatches(['path' => 'ISUI/ISContextMenu'], 0);

        $b = new RequireFailedProblem();
        $b->setMatches(['path' => 'ISUI/ISContextMenu'], 0);

        $c = new RequireFailedProblem();
        $c->setMatches(['path' => 'ISUI/ISInventoryPaneContextMenu'], 0);

        $this->assertTrue($a->isEqual($b));
        $this->assertFalse($a->isEqual($c));
    }

    // ── FunctionMissingProblem ────────────────────────────────────────────────

    public function testFunctionMissingPatternMatchesRealShape(): void
    {
        $line = '[16-04-26 12:34:56.789] ERROR: General      f:0, t:1234567890123, st:1,2,3,4> LuaManager.getFunctionObject> no such function "Vehicles.Init.Default".';
        $this->assertSame(1, preg_match(LuaWarningPattern::FUNCTION_MISSING, $line, $m));
        $this->assertSame('Vehicles.Init.Default', $m['name']);
    }

    public function testFunctionMissingExtractsFunctionName(): void
    {
        $problem = new FunctionMissingProblem();
        $problem->setMatches(['name' => 'SpecialLootSpawns.OnCreateRecipeMagazine'], 0);

        $this->assertSame('SpecialLootSpawns.OnCreateRecipeMagazine', $problem->getFunctionName());
        $this->assertStringContainsString('SpecialLootSpawns.OnCreateRecipeMagazine', $problem->getMessage());
        $this->assertSame(Severity::High, $problem->getSeverity());
    }

    public function testFunctionMissingCoalescesBySameName(): void
    {
        $a = new FunctionMissingProblem();
        $a->setMatches(['name' => 'Vehicles.Init.Default'], 0);

        $b = new FunctionMissingProblem();
        $b->setMatches(['name' => 'Vehicles.Init.Default'], 0);

        $c = new FunctionMissingProblem();
        $c->setMatches(['name' => 'Recipe.OnGiveXP.Cooking15'], 0);

        $this->assertTrue($a->isEqual($b));
        $this->assertFalse($a->isEqual($c));
    }

    // ── RecursiveRequireProblem ───────────────────────────────────────────────

    public function testRecursiveRequirePatternMatchesRealShape(): void
    {
        $line = '[16-04-26 12:34:56.789] WARN : Lua          f:0, t:1234567890123, st:1,2,3,4> at Lua(Vanilla).Features.lua> recursive require(): /project-zomboid/media/lua/server/WorldGen/WorldGen.lua.';
        $this->assertSame(1, preg_match(LuaWarningPattern::RECURSIVE_REQUIRE, $line, $m));
        $this->assertSame('/project-zomboid/media/lua/server/WorldGen/WorldGen.lua', $m['path']);
    }

    public function testRecursiveRequireExtractsPathAndSeverity(): void
    {
        $problem = new RecursiveRequireProblem();
        $problem->setMatches(['path' => '/media/lua/server/WorldGen/WorldGen.lua'], 0);

        $this->assertStringContainsString('/media/lua/server/WorldGen/WorldGen.lua', $problem->getMessage());
        $this->assertSame(Severity::High, $problem->getSeverity());
    }

    public function testRecursiveRequireCoalescesBySamePath(): void
    {
        $a = new RecursiveRequireProblem();
        $a->setMatches(['path' => '/media/lua/server/WorldGen/WorldGen.lua'], 0);

        $b = new RecursiveRequireProblem();
        $b->setMatches(['path' => '/media/lua/server/WorldGen/WorldGen.lua'], 0);

        $c = new RecursiveRequireProblem();
        $c->setMatches(['path' => '/media/lua/server/WorldGen/Biomes.lua'], 0);

        $this->assertTrue($a->isEqual($b));
        $this->assertFalse($a->isEqual($c));
    }
}
