<?php

namespace IndifferentKetchup\CodexPz\Test\Tests\Games\ProjectZomboid\Analyser;

use IndifferentKetchup\CodexPz\Analyser\Analyser;
use IndifferentKetchup\CodexPz\Analyser\AnalyserInterface;
use IndifferentKetchup\CodexPz\Analyser\CompositeAnalyser;
use IndifferentKetchup\CodexPz\Analyser\ProjectZomboid\ErrorContextAnalyser;
use IndifferentKetchup\CodexPz\Analysis\Analysis;
use IndifferentKetchup\CodexPz\Analysis\AnalysisInterface;
use IndifferentKetchup\CodexPz\Analysis\InsightInterface;
use IndifferentKetchup\CodexPz\Analysis\ProjectZomboid\ErrorContextProblem;
use IndifferentKetchup\CodexPz\Analysis\ProjectZomboid\ErrorContextTruncatedInformation;
use IndifferentKetchup\CodexPz\Analysis\ProjectZomboid\JavaExceptionProblem;
use IndifferentKetchup\CodexPz\Log\AnalysableLog;
use IndifferentKetchup\CodexPz\Log\Entry;
use IndifferentKetchup\CodexPz\Log\EntryInterface;
use IndifferentKetchup\CodexPz\Log\Level;
use IndifferentKetchup\CodexPz\Log\Line;
use PHPUnit\Framework\TestCase;

class ErrorContextAnalyserTest extends TestCase
{
    /**
     * Build an in-memory AnalysableLog with $count entries; entries whose
     * 1-based index is in $errorIndices are tagged Level::ERROR, the rest
     * Level::INFO. Anonymous AnalysableLog subclass keeps the fixture
     * inline since we exercise the analyser directly via setLog().
     *
     * @param int[] $errorIndices 1-based entry indices to mark as ERROR
     */
    private function makeLog(array $errorIndices, int $count): AnalysableLog
    {
        $errorSet = array_flip($errorIndices);
        $log = new class extends AnalysableLog {
            public static function getDefaultAnalyser(): AnalyserInterface
            {
                return new ErrorContextAnalyser();
            }
        };
        for ($n = 1; $n <= $count; $n++) {
            $level = isset($errorSet[$n]) ? Level::ERROR : Level::INFO;
            $entry = (new Entry())
                ->setLevel($level)
                ->addLine(new Line($n, sprintf('line %d', $n)));
            $log->addEntry($entry);
        }
        return $log;
    }

    public function testEmitsThreeNonOverlappingWindows(): void
    {
        $log = $this->makeLog([10, 50, 95], 100);
        $analysis = (new ErrorContextAnalyser())->setLog($log)->analyse();

        $problems = $analysis->getFilteredInsights(ErrorContextProblem::class);
        $this->assertCount(3, $problems);

        $this->assertSame(10, $problems[0]->getEntryIndex());
        $this->assertSame(50, $problems[1]->getEntryIndex());
        $this->assertSame(95, $problems[2]->getEntryIndex());

        // First hit (entry 10): 9 entries before (1..9), 20 after (11..30).
        $this->assertCount(9, $problems[0]->getBefore());
        $this->assertCount(20, $problems[0]->getAfter());

        // Second hit (entry 50): clipped to 19 before (31..49), 20 after (51..70).
        $this->assertCount(19, $problems[1]->getBefore());
        $this->assertCount(20, $problems[1]->getAfter());

        // Third hit (entry 95): clipped to 20 before (75..94), 5 after (96..100).
        $this->assertCount(20, $problems[2]->getBefore());
        $this->assertCount(5, $problems[2]->getAfter());

        // Total window per hit never exceeds 1 + CONTEXT_BEFORE + CONTEXT_AFTER = 41.
        foreach ($problems as $problem) {
            $this->assertLessThanOrEqual(ErrorContextAnalyser::CONTEXT_BEFORE, count($problem->getBefore()));
            $this->assertLessThanOrEqual(ErrorContextAnalyser::CONTEXT_AFTER, count($problem->getAfter()));
            $this->assertLessThanOrEqual(41, count($problem->getContext()));
        }

        // No entry appears in two problems' context arrays.
        $seen = [];
        foreach ($problems as $problem) {
            foreach ([...$problem->getBefore(), ...$problem->getAfter()] as $entry) {
                $id = spl_object_id($entry);
                $this->assertArrayNotHasKey($id, $seen, 'Entry duplicated across problem context arrays');
                $seen[$id] = true;
            }
        }
    }

    public function testMergesAdjacentWindowsWhenWithinContextRange(): void
    {
        // Errors 5 entries apart; without merge their windows would
        // overlap heavily.
        $log = $this->makeLog([10, 15], 50);
        $analysis = (new ErrorContextAnalyser())->setLog($log)->analyse();

        $problems = $analysis->getFilteredInsights(ErrorContextProblem::class);
        $this->assertCount(2, $problems);

        // First hit: 9 before (1..9), 20 after (11..30). lastEmittedIndex=29 (0-based).
        $this->assertCount(9, $problems[0]->getBefore());
        $this->assertCount(20, $problems[0]->getAfter());

        // Second hit at entry 15 (i=14). beforeStart clamped past i so before is empty.
        // afterStart=max(30, 15)=30, afterEnd=min(49, 34)=34, so after=entries 31..35
        // (5 entries, all unseen).
        $this->assertCount(0, $problems[1]->getBefore());
        $this->assertCount(5, $problems[1]->getAfter());

        // Confirm no entry appears in both problems' context arrays.
        $first = [...$problems[0]->getBefore(), ...$problems[0]->getAfter()];
        $second = [...$problems[1]->getBefore(), ...$problems[1]->getAfter()];
        foreach ($second as $entry) {
            $this->assertNotContains($entry, $first, 'Entry duplicated across merged windows');
        }
    }

    public function testTruncatesAtHitCap(): void
    {
        // 600 consecutive ERROR entries — analyser should cap emission at
        // HIT_CAP and add exactly one truncation Information.
        $log = $this->makeLog(range(1, 600), 600);
        $analysis = (new ErrorContextAnalyser())->setLog($log)->analyse();

        $problems = $analysis->getFilteredInsights(ErrorContextProblem::class);
        $this->assertCount(ErrorContextAnalyser::HIT_CAP, $problems);

        $information = $analysis->getFilteredInsights(ErrorContextTruncatedInformation::class);
        $this->assertCount(1, $information);
        $this->assertSame(ErrorContextAnalyser::HIT_CAP, $information[0]->getHitCap());
    }

    /**
     * A specific problem covering the same entry suppresses the
     * ErrorContextProblem after composite-merge postProcessAnalysis.
     */
    public function testSpecificProblemSuppressesSameEntryErrorContext(): void
    {
        // Log with two entries: one ERROR at index 1, one INFO at index 2.
        $log = $this->makeLog([1], 2);

        // ErrorContextAnalyser produces ErrorContextProblem for the error entry.
        $ecAnalyser = new ErrorContextAnalyser();

        // A "specific" analyser that produces a JavaExceptionProblem at the
        // same entry (entry at index 0, which is the first ERROR entry).
        $specificEntry = null;
        foreach ($log as $entry) {
            if ($entry->getLevel()->asInt() <= Level::ERROR->asInt()) {
                $specificEntry = $entry;
                break;
            }
        }
        $this->assertNotNull($specificEntry);

        $specificAnalyser = new class ($specificEntry) extends Analyser {
            private EntryInterface $entry;

            public function __construct(EntryInterface $entry)
            {
                $this->entry = $entry;
            }

            public function analyse(): AnalysisInterface
            {
                $analysis = new Analysis();
                $problem = (new JavaExceptionProblem())
                    ->setExceptionClass('java.lang.Exception')
                    ->setEntry($this->entry);
                $analysis->addInsight($problem);
                return $analysis;
            }
        };

        $composite = new CompositeAnalyser($ecAnalyser, $specificAnalyser);
        $composite->setLog($log);
        $result = $composite->analyse();

        $ecpCount = count($result->getFilteredInsights(ErrorContextProblem::class));
        $this->assertSame(0, $ecpCount,
            'ErrorContextProblem must be suppressed when a specific problem covers the same entry');

        $specificCount = count($result->getFilteredInsights(JavaExceptionProblem::class));
        $this->assertSame(1, $specificCount,
            'The specific problem must remain visible after suppression');
    }

    /**
     * A specific problem covering an adjacent entry also suppresses the
     * ErrorContextProblem after composite-merge postProcessAnalysis.
     */
    public function testSpecificProblemSuppressesAdjacentEntryErrorContext(): void
    {
        // Log with 3 entries: INFO at 1, ERROR at 2, INFO at 3.
        $log = $this->makeLog([2], 3);

        $entries = [];
        foreach ($log as $entry) {
            $entries[] = $entry;
        }

        $ecAnalyser = new ErrorContextAnalyser();

        $specificAnalyser = new class ($entries) extends Analyser {
            private array $entries;

            public function __construct(array $entries)
            {
                $this->entries = $entries;
            }

            public function analyse(): AnalysisInterface
            {
                $analysis = new Analysis();
                $problem = (new JavaExceptionProblem())
                    ->setExceptionClass('java.lang.Exception')
                    ->setEntry($this->entries[0] ?? null);
                $analysis->addInsight($problem);
                return $analysis;
            }
        };

        $composite = new CompositeAnalyser($ecAnalyser, $specificAnalyser);
        $composite->setLog($log);
        $result = $composite->analyse();

        $ecpCount = count($result->getFilteredInsights(ErrorContextProblem::class));
        $this->assertSame(0, $ecpCount,
            'ErrorContextProblem must be suppressed when a specific problem is adjacent');

        $specificCount = count($result->getFilteredInsights(JavaExceptionProblem::class));
        $this->assertSame(1, $specificCount,
            'The specific problem must remain visible after adjacent suppression');
    }
}
