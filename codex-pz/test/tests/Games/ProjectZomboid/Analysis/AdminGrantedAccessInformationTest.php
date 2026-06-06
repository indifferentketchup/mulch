<?php

namespace IndifferentKetchup\CodexPz\Test\Tests\Games\ProjectZomboid\Analysis;

use IndifferentKetchup\CodexPz\Analysis\ProjectZomboid\AdminGrantedAccessInformation;
use IndifferentKetchup\CodexPz\Pattern\ProjectZomboid\AdminPattern;
use PHPUnit\Framework\TestCase;

class AdminGrantedAccessInformationTest extends TestCase
{
    public function testGetPatternsReturnsEntryRegex(): void
    {
        $this->assertSame([AdminPattern::GRANTED_ACCESS_ENTRY], AdminGrantedAccessInformation::getPatterns());
    }

    public function testEntryRegexMatchesFullLine(): void
    {
        $line = "[16-04-26 18:35:10.000] AdminUser granted admin access level on Player1.";
        $this->assertSame(1, preg_match(AdminPattern::GRANTED_ACCESS_ENTRY, $line, $m));

        $insight = new AdminGrantedAccessInformation();
        $insight->setMatches($m, 0);

        $this->assertSame('Admin granted access', $insight->getLabel());
        $this->assertSame('AdminUser granted admin to Player1', $insight->getValue());
    }
}
