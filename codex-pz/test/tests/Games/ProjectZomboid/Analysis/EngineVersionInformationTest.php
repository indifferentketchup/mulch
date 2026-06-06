<?php

namespace IndifferentKetchup\CodexPz\Test\Tests\Games\ProjectZomboid\Analysis;

use IndifferentKetchup\CodexPz\Analysis\ProjectZomboid\EngineVersionInformation;
use IndifferentKetchup\CodexPz\Pattern\ProjectZomboid\DebugServerPattern;
use PHPUnit\Framework\TestCase;

class EngineVersionInformationTest extends TestCase
{
    public function testGetPatternsReturnsTheVersionRegex(): void
    {
        $this->assertSame([DebugServerPattern::VERSION], EngineVersionInformation::getPatterns());
    }

    public function testSetMatchesPopulatesLabelAndValue(): void
    {
        $line = '[16-04-26 00:00:42.407] LOG  : General      f:0, t:1776297642406, st:48,648,157,584> version=42.16.3 0000000000000000000000000000000000000000 2026-04-08 11:54:01 (ZB) demo=false.';
        $this->assertSame(1, preg_match(DebugServerPattern::VERSION, $line, $matches));

        $insight = new EngineVersionInformation();
        $insight->setMatches($matches, 0);

        $this->assertSame('Engine version', $insight->getLabel());
        $this->assertSame('42.16.3 (build 0000000000000000000000000000000000000000, 2026-04-08 11:54:01)', $insight->getValue());
        $this->assertSame('Engine version: 42.16.3 (build 0000000000000000000000000000000000000000, 2026-04-08 11:54:01)', $insight->getMessage());
    }
}
