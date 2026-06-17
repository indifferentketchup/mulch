<?php

namespace IndifferentKetchup\CodexPz\Test\Tests\Analysis;

use IndifferentKetchup\CodexPz\Analysis\Attribution;
use IndifferentKetchup\CodexPz\Analysis\AttributionConfidence;
use IndifferentKetchup\CodexPz\Analysis\InsightInterface;
use IndifferentKetchup\CodexPz\Analysis\Kind;
use IndifferentKetchup\CodexPz\Analysis\LlmVerdict;
use IndifferentKetchup\CodexPz\Analysis\ModAttribution;
use IndifferentKetchup\CodexPz\Analysis\ModAttributedInsightInterface;
use IndifferentKetchup\CodexPz\Analysis\RankCalculator;
use IndifferentKetchup\CodexPz\Analysis\Severity;
use IndifferentKetchup\CodexPz\Analysis\SeverityAwareInsightInterface;
use IndifferentKetchup\CodexPz\Log\EntryInterface;
use IndifferentKetchup\CodexPz\Log\Level;
use PHPUnit\Framework\TestCase;

class RankCalculatorTest extends TestCase
{
    private function mockInsight(array $overrides = []): InsightInterface
    {
        return new class (
            $overrides['kind'] ?? Kind::Unknown,
            $overrides['attribution'] ?? Attribution::Unattributed,
            $overrides['severity'] ?? null,
            $overrides['confidence'] ?? null,
            $overrides['count'] ?? 1,
            $overrides['level'] ?? null,
            $overrides['causeChain'] ?? null,
            $overrides['hasSolution'] ?? false,
        ) implements InsightInterface, SeverityAwareInsightInterface, ModAttributedInsightInterface {
            private Kind $kind;
            private Attribution $attribution;
            private ?Severity $severity;
            private ?AttributionConfidence $confidence;
            private int $count;
            private ?Level $level;
            private ?string $causeChain;
            private bool $hasSolution;

            public function __construct(
                Kind $kind,
                Attribution $attribution,
                ?Severity $severity,
                ?AttributionConfidence $confidence,
                int $count,
                ?Level $level,
                ?string $causeChain,
                bool $hasSolution,
            ) {
                $this->kind = $kind;
                $this->attribution = $attribution;
                $this->severity = $severity;
                $this->confidence = $confidence;
                $this->count = $count;
                $this->level = $level;
                $this->causeChain = $causeChain;
                $this->hasSolution = $hasSolution;
            }

            public function getKind(): Kind { return $this->kind; }
            public function setKind(Kind $kind): static { $this->kind = $kind; return $this; }
            public function getAttribution(): Attribution { return $this->attribution; }
            public function setAttribution(Attribution $attribution): static { $this->attribution = $attribution; return $this; }
            public function getRankScore(): int { return RankCalculator::applyLlmAdjustment(RankCalculator::compute($this), null); }
            public function isGated(): bool { return false; }
            public function setGated(?bool $gated): static { return $this; }
            public function getSeverity(): Severity { return $this->severity ?? Severity::Low; }
            public function getMessage(): string { return 'test'; }
            public function __toString(): string { return 'test'; }
            public function setEntry(EntryInterface $entry): static { return $this; }
            public function getEntry(): ?EntryInterface {
                if ($this->level === null) { return null; }
                $entry = new \IndifferentKetchup\CodexPz\Log\Entry();
                $entry->setLevel($this->level);
                return $entry;
            }
            public function isEqual(InsightInterface $insight): bool { return false; }
            public function increaseCounter(): static { return $this; }
            public function getCounterValue(): int { return $this->count; }
            public function getFingerprint(): string { return 'test'; }
            public function setAnalysis(\IndifferentKetchup\CodexPz\Analysis\AnalysisInterface $analysis): static { return $this; }
            public function getAnalysis(): ?\IndifferentKetchup\CodexPz\Analysis\AnalysisInterface { return null; }
            public function jsonSerialize(): array { return []; }
            public function getModAttribution(): ?ModAttribution {
                if ($this->confidence === null) { return null; }
                return new ModAttribution('TestMod', null, null, $this->confidence);
            }
        };
    }

    public function testDirectLuaRuntimeRanksHigh(): void
    {
        $insight = $this->mockInsight([
            'kind' => Kind::LuaRuntime,
            'attribution' => Attribution::Attributed,
            'severity' => Severity::High,
            'confidence' => AttributionConfidence::Direct,
            'count' => 1,
            'level' => Level::ERROR,
        ]);
        $rank = $insight->getRankScore();
        $this->assertGreaterThan(Severity::Medium->value, $rank,
            'Direct lua-runtime should rank above Medium');
    }

    public function testUnattributedClamp(): void
    {
        $insight = $this->mockInsight([
            'kind' => Kind::JavaException,
            'attribution' => Attribution::Unattributed,
            'severity' => Severity::High,
            'count' => 1,
            'level' => Level::ERROR,
        ]);
        $rank = $insight->getRankScore();
        $this->assertLessThanOrEqual(Severity::Low->value, $rank,
            'Unattributed insight rank must be clamped to at most Severity::Low');
    }

    public function testEngineNoiseFloor(): void
    {
        $insight = $this->mockInsight([
            'kind' => Kind::EngineNoise,
            'attribution' => Attribution::Unattributed,
            'severity' => Severity::Noise,
            'count' => 1,
            'level' => Level::WARNING,
        ]);
        $rank = $insight->getRankScore();
        $this->assertLessThanOrEqual(Severity::Noise->value, $rank,
            'Engine-noise insight rank must be clamped to at most Severity::Noise');
    }

    public function testFrequencyDampCapped(): void
    {
        $insight = $this->mockInsight([
            'kind' => Kind::LuaRuntime,
            'attribution' => Attribution::Attributed,
            'severity' => Severity::Medium,
            'confidence' => AttributionConfidence::Direct,
            'count' => 150,
            'level' => Level::ERROR,
        ]);
        $rank = $insight->getRankScore();
        $highFreq = $this->mockInsight([
            'kind' => Kind::LuaRuntime,
            'attribution' => Attribution::Attributed,
            'severity' => Severity::Medium,
            'confidence' => AttributionConfidence::Direct,
            'count' => 10000,
            'level' => Level::ERROR,
        ]);
        $this->assertSame($rank, $highFreq->getRankScore(),
            'Frequency damp must cap at PHP_INT_MAX bucket; 150 and 10000 should have same damp');
    }

    public function testLlmAdjustmentClamp(): void
    {
        $baseRank = Severity::Low->value;
        $veryLow = RankCalculator::applyLlmAdjustment($baseRank, new LlmVerdict('performance', 'info', 0.9));
        $this->assertGreaterThanOrEqual(Severity::Noise->value, $veryLow,
            'LLM-adjusted rank must not fall below Severity::Noise');
        $veryHigh = RankCalculator::applyLlmAdjustment($baseRank, new LlmVerdict('corrupt_save', 'problem', 0.9));
        $this->assertLessThanOrEqual(Severity::Critical->value, $veryHigh,
            'LLM-adjusted rank must not exceed Severity::Critical');
    }

    public function testLowConfidenceVerdictNoEffect(): void
    {
        $rank = RankCalculator::compute($this->mockInsight());
        $adjusted = RankCalculator::applyLlmAdjustment($rank, new LlmVerdict('corrupt_save', 'problem', 0.3));
        $this->assertSame($rank, $adjusted,
            'LLM verdict with confidence < 0.5 must not change rank');
    }
}
