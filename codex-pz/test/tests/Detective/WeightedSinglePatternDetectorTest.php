<?php

namespace IndifferentKetchup\CodexPz\Test\Tests\Detective;

use IndifferentKetchup\CodexPz\Detective\WeightedSinglePatternDetector;
use IndifferentKetchup\CodexPz\Log\File\StringLogFile;
use PHPUnit\Framework\TestCase;

class WeightedSinglePatternDetectorTest extends TestCase
{
    public function testDetect(): void
    {
        $this->assertEquals(1, (new WeightedSinglePatternDetector())
            ->setLogFile(new \IndifferentKetchup\CodexPz\Log\File\PathLogFile(__DIR__ . '/../../data/simple.log'))
            ->setPattern('/This/')
            ->setWeight(1)
            ->detect()
        );

        $this->assertEquals(0.5, (new WeightedSinglePatternDetector())
            ->setLogFile(new \IndifferentKetchup\CodexPz\Log\File\PathLogFile(__DIR__ . '/../../data/simple.log'))
            ->setPattern('/This/')
            ->setWeight(0.5)
            ->detect()
        );

        $this->assertEquals(0, (new WeightedSinglePatternDetector())
            ->setLogFile(new \IndifferentKetchup\CodexPz\Log\File\PathLogFile(__DIR__ . '/../../data/simple.log'))
            ->setPattern('/This/')
            ->setWeight(0)
            ->detect()
        );

        $this->assertFalse((new WeightedSinglePatternDetector())
            ->setLogFile(new StringLogFile("You cannot detect this."))
            ->setPattern('/missing/')
            ->detect()
        );
    }
}
