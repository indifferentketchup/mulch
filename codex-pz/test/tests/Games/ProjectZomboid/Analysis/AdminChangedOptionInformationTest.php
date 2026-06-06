<?php

namespace IndifferentKetchup\CodexPz\Test\Tests\Games\ProjectZomboid\Analysis;

use IndifferentKetchup\CodexPz\Analysis\ProjectZomboid\AdminChangedOptionInformation;
use IndifferentKetchup\CodexPz\Pattern\ProjectZomboid\AdminPattern;
use PHPUnit\Framework\TestCase;

class AdminChangedOptionInformationTest extends TestCase
{
    public function testGetPatternsReturnsEntryRegex(): void
    {
        $this->assertSame([AdminPattern::CHANGED_OPTION_ENTRY], AdminChangedOptionInformation::getPatterns());
    }

    public function testEntryRegexMatchesFullLine(): void
    {
        $line = "[16-04-26 18:36:15.500] AdminUser changed option AnnounceDeath=true.";
        $this->assertSame(1, preg_match(AdminPattern::CHANGED_OPTION_ENTRY, $line, $m));

        $insight = new AdminChangedOptionInformation();
        $insight->setMatches($m, 0);

        $this->assertSame('Admin changed option', $insight->getLabel());
        $this->assertSame('AdminUser set AnnounceDeath=true', $insight->getValue());
    }
}
