<?php

namespace IndifferentKetchup\CodexPz\Test\Tests\Games\ProjectZomboid\Analysis;

use IndifferentKetchup\CodexPz\Analysis\ProjectZomboid\ServerExceptionProblem;
use IndifferentKetchup\CodexPz\Pattern\ProjectZomboid\DebugServerPattern;
use PHPUnit\Framework\TestCase;

class ServerExceptionProblemTest extends TestCase
{
    public function testGetPatternsReturnsTheExceptionRegex(): void
    {
        $this->assertSame([DebugServerPattern::EXCEPTION], ServerExceptionProblem::getPatterns());
    }

    public function testSetMatchesCapturesTypeAndBodyAcrossLines(): void
    {
        $entryText = "[16-04-26 00:01:19.080] ERROR: General      f:0, t:1776297679080, st:48,648,194,258> DebugFileWatcher.registerDir> Exception thrown\n"
            . "\tjava.nio.file.NoSuchFileException: /placeholder/config/mods at UnixException.translateToIOException(null:-1).\n"
            . "\tStack trace:\n"
            . "\t\tjava.base/sun.nio.fs.UnixException.translateToIOException(Unknown Source)";

        $this->assertSame(1, preg_match(DebugServerPattern::EXCEPTION, $entryText, $matches));

        $problem = new ServerExceptionProblem();
        $problem->setMatches($matches, 0);

        $this->assertSame('java.nio.file.NoSuchFileException', $problem->getExceptionType());
        $this->assertStringContainsString('Stack trace', $problem->getBody());
        $this->assertStringContainsString('java.base/sun.nio.fs.UnixException', $problem->getBody());
    }

    public function testIsEqualCoalescesSameTypeRegardlessOfBody(): void
    {
        $a = $this->problemFor('java.io.IOException', 'body one');
        $b = $this->problemFor('java.io.IOException', 'body two completely different');
        $c = $this->problemFor('java.lang.RuntimeException', 'body one');

        $this->assertTrue($a->isEqual($b));
        $this->assertFalse($a->isEqual($c));
    }

    public function testNestedExceptionTypeNamesAreSupported(): void
    {
        $entryText = "[16-04-26 00:01:45.937] ERROR: WorldGen     f:0, t:1776297705937, st:48,648,221,115> IsoPropertyType.lookupOrDefaultStr> Exception thrown\n"
            . "\tzombie.core.properties.IsoPropertyType\$IsoPropertyTypeNotFoundException: Property Name not found: ladderW";

        $this->assertSame(1, preg_match(DebugServerPattern::EXCEPTION, $entryText, $matches));

        $problem = new ServerExceptionProblem();
        $problem->setMatches($matches, 0);

        $this->assertSame('zombie.core.properties.IsoPropertyType$IsoPropertyTypeNotFoundException', $problem->getExceptionType());
    }

    private function problemFor(string $type, string $body): ServerExceptionProblem
    {
        $problem = new ServerExceptionProblem();
        $problem->setMatches(['type' => $type, 'body' => $body], 0);
        return $problem;
    }
}
