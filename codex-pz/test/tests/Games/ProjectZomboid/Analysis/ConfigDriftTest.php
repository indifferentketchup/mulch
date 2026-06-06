<?php

namespace IndifferentKetchup\CodexPz\Test\Tests\Games\ProjectZomboid\Analysis;

use IndifferentKetchup\CodexPz\Analysis\EngineNoiseInsightInterface;
use IndifferentKetchup\CodexPz\Analysis\ProjectZomboid\UnknownItemParamInformation;
use IndifferentKetchup\CodexPz\Analysis\ProjectZomboid\UnknownSandboxOptionInformation;
use IndifferentKetchup\CodexPz\Analysis\Severity;
use IndifferentKetchup\CodexPz\Pattern\ProjectZomboid\ConfigDriftPattern;
use PHPUnit\Framework\TestCase;

class ConfigDriftTest extends TestCase
{
    // ── UnknownSandboxOptionInformation ───────────────────────────────────────

    public function testSandboxOptionPatternMatchesRealShape(): void
    {
        $line = '[16-04-26 12:34:56.789] LOG  : General      f:0, t:1234567890123, st:1,2,3,4> ERROR unknown SandboxOption "PhunZones.Widget".';
        $this->assertSame(1, preg_match(ConfigDriftPattern::UNKNOWN_SANDBOX_OPTION, $line, $m));
        $this->assertSame('PhunZones.Widget', $m['option']);
    }

    public function testSandboxOptionExtractsOptionNameAndSeverity(): void
    {
        $info = new UnknownSandboxOptionInformation();
        $info->setMatches(['option' => 'PhunZones.ShowDifficulty'], 0);

        $this->assertSame('Unknown sandbox option', $info->getLabel());
        $this->assertSame('PhunZones.ShowDifficulty', $info->getValue());
        $this->assertSame(Severity::Low, $info->getSeverity());
    }

    public function testSandboxOptionIsNotEngineNoise(): void
    {
        $this->assertNotInstanceOf(EngineNoiseInsightInterface::class, new UnknownSandboxOptionInformation());
    }

    public function testSandboxOptionMessageContainsOptionName(): void
    {
        $info = new UnknownSandboxOptionInformation();
        $info->setMatches(['option' => 'PhunZones.Widget'], 0);

        $this->assertStringContainsString('PhunZones.Widget', $info->getMessage());
    }

    public function testSandboxOptionCoalescesBySameOptionName(): void
    {
        $a = new UnknownSandboxOptionInformation();
        $a->setMatches(['option' => 'PhunZones.Widget'], 0);

        $b = new UnknownSandboxOptionInformation();
        $b->setMatches(['option' => 'PhunZones.Widget'], 0);

        $c = new UnknownSandboxOptionInformation();
        $c->setMatches(['option' => 'PhunZones.MaxDifficulty'], 0);

        $this->assertTrue($a->isEqual($b));
        $this->assertFalse($a->isEqual($c));
    }

    // ── UnknownItemParamInformation ───────────────────────────────────────────

    public function testItemParamPatternMatchesRealShape(): void
    {
        $line = '[16-04-26 12:34:56.789] LOG  : General      f:0, t:1234567890123, st:1,2,3,4> adding unknown item param "DrumMagType" = "Base.556Drum".';
        $this->assertSame(1, preg_match(ConfigDriftPattern::UNKNOWN_ITEM_PARAM, $line, $m));
        $this->assertSame('DrumMagType', $m['param']);
    }

    public function testItemParamExtractsParamNameAndSeverity(): void
    {
        $info = new UnknownItemParamInformation();
        $info->setMatches(['param' => 'hidden'], 0);

        $this->assertSame('Unknown item param', $info->getLabel());
        $this->assertSame('hidden', $info->getValue());
        $this->assertSame(Severity::Noise, $info->getSeverity());
    }

    public function testItemParamImplementsEngineNoise(): void
    {
        $this->assertInstanceOf(EngineNoiseInsightInterface::class, new UnknownItemParamInformation());
    }

    public function testItemParamCoalescesBySameParamName(): void
    {
        $a = new UnknownItemParamInformation();
        $a->setMatches(['param' => 'DrumMagType'], 0);

        $b = new UnknownItemParamInformation();
        $b->setMatches(['param' => 'DrumMagType'], 0);

        $c = new UnknownItemParamInformation();
        $c->setMatches(['param' => 'HFO_AttachmentSlot'], 0);

        $this->assertTrue($a->isEqual($b));
        $this->assertFalse($a->isEqual($c));
    }
}
