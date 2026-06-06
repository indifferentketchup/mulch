<?php

namespace IndifferentKetchup\CodexPz\Test\Tests\Games\ProjectZomboid\Analysis;

use IndifferentKetchup\CodexPz\Analysis\ProjectZomboid\AdminTeleportedInformation;
use IndifferentKetchup\CodexPz\Pattern\ProjectZomboid\AdminPattern;
use PHPUnit\Framework\TestCase;

class AdminTeleportedInformationTest extends TestCase
{
    public function testGetPatternsReturnsEntryRegex(): void
    {
        $this->assertSame([AdminPattern::TELEPORTED_ENTRY], AdminTeleportedInformation::getPatterns());
    }

    public function testEntryRegexMatchesPositiveZ(): void
    {
        $line = "[16-04-26 18:38:00.225] AdminUser teleported Player1 to 1100,2200,0.";
        $this->assertSame(1, preg_match(AdminPattern::TELEPORTED_ENTRY, $line, $m));

        $insight = new AdminTeleportedInformation();
        $insight->setMatches($m, 0);

        $this->assertSame('Admin teleported', $insight->getLabel());
        $this->assertSame('AdminUser teleported Player1 to 1100,2200,0', $insight->getValue());
    }

    public function testEntryRegexHandlesNegativeZ(): void
    {
        $line = "[16-04-26 18:39:15.500] AdminUser teleported Player2 to 1100,2200,-1.";
        $this->assertSame(1, preg_match(AdminPattern::TELEPORTED_ENTRY, $line, $m));
        $this->assertSame('-1', $m['z']);
    }
}
