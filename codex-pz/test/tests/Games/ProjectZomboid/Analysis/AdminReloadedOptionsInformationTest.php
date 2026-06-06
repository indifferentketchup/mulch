<?php

namespace IndifferentKetchup\CodexPz\Test\Tests\Games\ProjectZomboid\Analysis;

use IndifferentKetchup\CodexPz\Analysis\ProjectZomboid\AdminReloadedOptionsInformation;
use IndifferentKetchup\CodexPz\Pattern\ProjectZomboid\AdminPattern;
use PHPUnit\Framework\TestCase;

class AdminReloadedOptionsInformationTest extends TestCase
{
    public function testGetPatternsReturnsEntryRegex(): void
    {
        $this->assertSame([AdminPattern::RELOADED_OPTIONS_ENTRY], AdminReloadedOptionsInformation::getPatterns());
    }

    public function testEntryRegexMatchesFullLine(): void
    {
        $line = "[16-04-26 18:37:00.014] AdminUser reloaded options.";
        $this->assertSame(1, preg_match(AdminPattern::RELOADED_OPTIONS_ENTRY, $line, $m));

        $insight = new AdminReloadedOptionsInformation();
        $insight->setMatches($m, 0);

        $this->assertSame('Admin reloaded options', $insight->getLabel());
        $this->assertSame('AdminUser', $insight->getValue());
    }
}
