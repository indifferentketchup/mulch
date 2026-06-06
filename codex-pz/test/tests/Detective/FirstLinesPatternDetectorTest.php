<?php

namespace IndifferentKetchup\CodexPz\Test\Tests\Detective;

use IndifferentKetchup\CodexPz\Detective\FirstLinesPatternDetector;
use IndifferentKetchup\CodexPz\Log\File\StringLogFile;
use PHPUnit\Framework\TestCase;

class FirstLinesPatternDetectorTest extends TestCase
{
    public function testReturnsConfiguredWeightWhenPatternMatchesWithinFirstNLines(): void
    {
        $content = implode("\n", [
            'header line one',
            'BANNER: Hytale Server v0.1.0',
            'tail line',
        ]);

        $result = (new FirstLinesPatternDetector())
            ->setLogFile(new StringLogFile($content))
            ->setPattern('/Hytale Server/')
            ->setLineCount(50)
            ->setWeight(0.7)
            ->detect();

        $this->assertSame(0.7, $result);
    }

    public function testReturnsFalseWhenPatternMatchesOutsideFirstNLines(): void
    {
        $lines = [];
        for ($i = 1; $i <= 9; $i++) {
            $lines[] = "filler line {$i}";
        }
        $lines[] = 'BANNER: Hytale Server v0.1.0';
        $content = implode("\n", $lines);

        $result = (new FirstLinesPatternDetector())
            ->setLogFile(new StringLogFile($content))
            ->setPattern('/Hytale Server/')
            ->setLineCount(5)
            ->setWeight(0.7)
            ->detect();

        $this->assertFalse($result);
    }

    public function testReturnsFalseWhenPatternMatchesNowhere(): void
    {
        $content = implode("\n", [
            'header line one',
            'header line two',
            'tail line',
        ]);

        $result = (new FirstLinesPatternDetector())
            ->setLogFile(new StringLogFile($content))
            ->setPattern('/Hytale Server/')
            ->setLineCount(50)
            ->setWeight(0.7)
            ->detect();

        $this->assertFalse($result);
    }

    public function testDetectReturnsFalseWhenPatternNotSet(): void
    {
        $result = (new FirstLinesPatternDetector())
            ->setLogFile(new StringLogFile("any content\n"))
            ->detect();

        $this->assertFalse($result);
    }

    public function testSetWeightRejectsNegativeValue(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new FirstLinesPatternDetector())->setWeight(-0.1);
    }

    public function testSetWeightRejectsValueAboveOne(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new FirstLinesPatternDetector())->setWeight(1.5);
    }
}
