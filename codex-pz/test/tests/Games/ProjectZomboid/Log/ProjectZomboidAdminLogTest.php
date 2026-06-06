<?php

namespace IndifferentKetchup\CodexPz\Test\Tests\Games\ProjectZomboid\Log;

use IndifferentKetchup\CodexPz\Detective\Detective;
use IndifferentKetchup\CodexPz\Log\File\PathLogFile;
use IndifferentKetchup\CodexPz\Log\ProjectZomboid\ProjectZomboidAdminLog;
use IndifferentKetchup\CodexPz\Pattern\ProjectZomboid\AdminPattern;
use PHPUnit\Framework\TestCase;

class ProjectZomboidAdminLogTest extends TestCase
{
    private function fixturePath(): string
    {
        return __DIR__ . '/../../../../src/Games/ProjectZomboid/fixtures/admin-minimal.txt';
    }

    public function testParsesEachLineAsAnEntry(): void
    {
        $log = (new ProjectZomboidAdminLog())->setLogFile(new PathLogFile($this->fixturePath()));
        $log->parse();

        $this->assertCount(12, $log->getEntries());
    }

    public function testAddedItemRegexExtracts(): void
    {
        $msg = "AdminUser added item Base.ShotgunShells in Player1's inventory";
        $this->assertSame(1, preg_match(AdminPattern::ADDED_ITEM, $msg, $m));
        $this->assertSame('AdminUser', $m['admin']);
        $this->assertSame('Base.ShotgunShells', $m['item']);
        $this->assertSame('Player1', $m['target']);
    }

    public function testAddedXpRegexExtracts(): void
    {
        $msg = "AdminUser added 750.0 Blunt xp's to Player1";
        $this->assertSame(1, preg_match(AdminPattern::ADDED_XP, $msg, $m));
        $this->assertSame('750.0', $m['amount']);
        $this->assertSame('Blunt', $m['skill']);
        $this->assertSame('Player1', $m['target']);
    }

    public function testGrantedAccessRegexExtracts(): void
    {
        $msg = "AdminUser granted admin access level on Player1";
        $this->assertSame(1, preg_match(AdminPattern::GRANTED_ACCESS, $msg, $m));
        $this->assertSame('admin', $m['level']);
        $this->assertSame('Player1', $m['target']);
    }

    public function testChangedOptionRegexExtracts(): void
    {
        $msg = "AdminUser changed option PVP=false";
        $this->assertSame(1, preg_match(AdminPattern::CHANGED_OPTION, $msg, $m));
        $this->assertSame('PVP', $m['option']);
        $this->assertSame('false', $m['value']);
    }

    public function testReloadedOptionsRegexExtracts(): void
    {
        $msg = "AdminUser reloaded options";
        $this->assertSame(1, preg_match(AdminPattern::RELOADED_OPTIONS, $msg, $m));
        $this->assertSame('AdminUser', $m['admin']);
    }

    public function testTeleportedRegexHandlesNegativeZ(): void
    {
        $msg = "AdminUser teleported Player2 to 1100,2200,-1";
        $this->assertSame(1, preg_match(AdminPattern::TELEPORTED, $msg, $m));
        $this->assertSame('Player2', $m['target']);
        $this->assertSame('-1', $m['z']);
    }

    public function testDetectiveDispatchesByContent(): void
    {
        $detective = (new Detective())
            ->setLogFile(new PathLogFile($this->fixturePath()))
            ->addPossibleLogClass(ProjectZomboidAdminLog::class);

        $this->assertInstanceOf(ProjectZomboidAdminLog::class, $detective->detect());
    }
}
