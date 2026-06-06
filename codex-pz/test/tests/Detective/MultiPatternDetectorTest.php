<?php

namespace IndifferentKetchup\CodexPz\Test\Tests\Detective;

use IndifferentKetchup\CodexPz\Detective\MultiPatternDetector;
use IndifferentKetchup\CodexPz\Log\File\StringLogFile;
use PHPUnit\Framework\TestCase;

class MultiPatternDetectorTest extends TestCase
{
    public function testDetectSinglePattern(): void
    {
        $this->assertTrue((new MultiPatternDetector())
            ->setLogFile(new StringLogFile("You can detect this."))
            ->addPattern('/detect/')
            ->detect()
        );
    }

    public function testDetectMultiplePatterns(): void
    {
        $this->assertTrue((new MultiPatternDetector())
            ->setLogFile(new StringLogFile("You can detect this and this."))
            ->addPattern('/detect/')
            ->addPattern('/and this/')
            ->detect()
        );
    }

    public function testNotDetectMissingFromMultiplePatterns(): void
    {
        $this->assertFalse((new MultiPatternDetector())
            ->setLogFile(new StringLogFile("You can detect this and this."))
            ->addPattern('/detect/')
            ->addPattern('/and this/')
            ->addPattern('/but not this/')
            ->detect()
        );
    }
}
