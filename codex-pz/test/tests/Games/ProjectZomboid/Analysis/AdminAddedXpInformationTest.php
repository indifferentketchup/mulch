<?php

namespace IndifferentKetchup\CodexPz\Test\Tests\Games\ProjectZomboid\Analysis;

use IndifferentKetchup\CodexPz\Analysis\ProjectZomboid\AdminAddedXpInformation;
use IndifferentKetchup\CodexPz\Pattern\ProjectZomboid\AdminPattern;
use PHPUnit\Framework\TestCase;

class AdminAddedXpInformationTest extends TestCase
{
    public function testGetPatternsReturnsEntryRegex(): void
    {
        $this->assertSame([AdminPattern::ADDED_XP_ENTRY], AdminAddedXpInformation::getPatterns());
    }

    public function testEntryRegexMatchesFullLine(): void
    {
        $line = "[16-04-26 18:34:00.500] AdminUser added 750.0 Blunt xp's to Player1.";
        $this->assertSame(1, preg_match(AdminPattern::ADDED_XP_ENTRY, $line, $m));

        $insight = new AdminAddedXpInformation();
        $insight->setMatches($m, 0);

        $this->assertSame('Admin added xp', $insight->getLabel());
        $this->assertSame('AdminUser added 750.0 Blunt xp to Player1', $insight->getValue());
    }

    public function testEntryRegexDoesNotMatchAddedItemLine(): void
    {
        $line = "[16-04-26 18:33:34.289] AdminUser added item Base.ShotgunShells in Player1's inventory.";
        $this->assertSame(0, preg_match(AdminPattern::ADDED_XP_ENTRY, $line));
    }
}
