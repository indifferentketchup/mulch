<?php

namespace IndifferentKetchup\CodexPz\Test\Tests\Games\ProjectZomboid\Analysis;

use IndifferentKetchup\CodexPz\Analysis\ProjectZomboid\AdminAddedItemInformation;
use IndifferentKetchup\CodexPz\Pattern\ProjectZomboid\AdminPattern;
use PHPUnit\Framework\TestCase;

class AdminAddedItemInformationTest extends TestCase
{
    public function testGetPatternsReturnsEntryRegex(): void
    {
        $this->assertSame([AdminPattern::ADDED_ITEM_ENTRY], AdminAddedItemInformation::getPatterns());
    }

    public function testEntryRegexMatchesFullLine(): void
    {
        $line = "[16-04-26 18:33:34.289] AdminUser added item Base.ShotgunShells in Player1's inventory.";
        $this->assertSame(1, preg_match(AdminPattern::ADDED_ITEM_ENTRY, $line, $m));

        $insight = new AdminAddedItemInformation();
        $insight->setMatches($m, 0);

        $this->assertSame('Admin added item', $insight->getLabel());
        $this->assertSame('AdminUser added Base.ShotgunShells to Player1', $insight->getValue());
    }

    public function testIsEqualCoalescesIdenticalAddedItem(): void
    {
        $a = $this->insightFor('AdminUser', 'Base.X', 'Player1');
        $b = $this->insightFor('AdminUser', 'Base.X', 'Player1');
        $c = $this->insightFor('AdminUser', 'Base.Y', 'Player1');

        $this->assertTrue($a->isEqual($b));
        $this->assertFalse($a->isEqual($c));
    }

    private function insightFor(string $admin, string $item, string $target): AdminAddedItemInformation
    {
        $insight = new AdminAddedItemInformation();
        $insight->setMatches([
            'admin' => $admin,
            'item' => $item,
            'target' => $target,
        ], 0);
        return $insight;
    }
}
