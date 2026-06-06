<?php

namespace IndifferentKetchup\CodexPz\Test\Tests\Games\ProjectZomboid\Analyser;

use IndifferentKetchup\CodexPz\Analysis\ProjectZomboid\EngineNoiseExceptionInformation;
use IndifferentKetchup\CodexPz\Analysis\ProjectZomboid\EngineVersionInformation;
use IndifferentKetchup\CodexPz\Analysis\ProjectZomboid\JavaExceptionProblem;
use IndifferentKetchup\CodexPz\Analysis\ProjectZomboid\ModLoadInformation;
use IndifferentKetchup\CodexPz\Analysis\ProjectZomboid\ModMissingProblem;
use IndifferentKetchup\CodexPz\Log\File\PathLogFile;
use IndifferentKetchup\CodexPz\Log\ProjectZomboid\ProjectZomboidServerLog;
use PHPUnit\Framework\TestCase;

class ServerLogAnalysisTest extends TestCase
{
    private function fixturePath(): string
    {
        return __DIR__ . '/../../../../src/Games/ProjectZomboid/fixtures/debug-server-minimal.txt';
    }

    public function testAnalyseProducesExpectedInsightSet(): void
    {
        $log = (new ProjectZomboidServerLog())->setLogFile(new PathLogFile($this->fixturePath()));
        $log->parse();
        $analysis = $log->analyse();

        $this->assertCount(1, $analysis->getFilteredInsights(EngineVersionInformation::class));
        $this->assertCount(3, $analysis->getFilteredInsights(ModLoadInformation::class));
        $this->assertCount(1, $analysis->getFilteredInsights(ModMissingProblem::class));
        // DebugFileWatcher NoSuchFile → EngineNoiseExceptionInformation (StackTrace child).
        $this->assertCount(1, $analysis->getFilteredInsights(EngineNoiseExceptionInformation::class));
        // IsoPropertyType → JavaExceptionProblem (StackTrace child).
        $this->assertCount(1, $analysis->getFilteredInsights(JavaExceptionProblem::class));
    }

    public function testAnalysisCarriesAttachedSolutionForMissingMod(): void
    {
        $log = (new ProjectZomboidServerLog())->setLogFile(new PathLogFile($this->fixturePath()));
        $log->parse();
        $analysis = $log->analyse();

        $missing = $analysis->getFilteredInsights(ModMissingProblem::class);
        $this->assertCount(1, $missing);
        $this->assertCount(1, $missing[0]->getSolutions());
    }

    public function testTwoDistinctExceptionsClassifyIntoCorrectTypes(): void
    {
        $log = (new ProjectZomboidServerLog())->setLogFile(new PathLogFile($this->fixturePath()));
        $log->parse();
        $analysis = $log->analyse();

        // DebugFileWatcher + NoSuchFileException → engine noise (not a crash).
        $noise = $analysis->getFilteredInsights(EngineNoiseExceptionInformation::class);
        $this->assertCount(1, $noise);
        $this->assertSame('java.nio.file.NoSuchFileException', $noise[0]->getExceptionClass());

        // IsoPropertyTypeNotFoundException → a generic Java exception problem.
        $java = $analysis->getFilteredInsights(JavaExceptionProblem::class);
        $this->assertCount(1, $java);
        $this->assertStringContainsString('IsoPropertyType', $java[0]->getExceptionClass());
    }
}
