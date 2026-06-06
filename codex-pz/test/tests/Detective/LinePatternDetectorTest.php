<?php

namespace IndifferentKetchup\CodexPz\Test\Tests\Detective;

use IndifferentKetchup\CodexPz\Detective\LinePatternDetector;
use PHPUnit\Framework\TestCase;

class LinePatternDetectorTest extends TestCase
{
    public function testDetect(): void
    {
        $this->assertEquals(5 / 7, (new LinePatternDetector())
            ->setLogFile(new \IndifferentKetchup\CodexPz\Log\File\PathLogFile(__DIR__ . '/../../data/simple.log'))
            ->setPattern('/information/')
            ->detect()
        );

        $this->assertFalse((new LinePatternDetector())
            ->setLogFile(new \IndifferentKetchup\CodexPz\Log\File\PathLogFile(__DIR__ . '/../../data/simple.log'))
            ->setPattern('/missing/')
            ->detect()
        );

        $this->assertEquals(1, (new LinePatternDetector())
            ->setLogFile(new \IndifferentKetchup\CodexPz\Log\File\PathLogFile(__DIR__ . '/../../data/simple.log'))
            ->setPattern('/This/')
            ->detect()
        );
    }
}
