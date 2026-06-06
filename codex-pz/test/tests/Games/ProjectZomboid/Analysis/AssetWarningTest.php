<?php

namespace IndifferentKetchup\CodexPz\Test\Tests\Games\ProjectZomboid\Analysis;

use IndifferentKetchup\CodexPz\Analysis\EngineNoiseInsightInterface;
use IndifferentKetchup\CodexPz\Analysis\ProjectZomboid\BufferOverflowInformation;
use IndifferentKetchup\CodexPz\Analysis\ProjectZomboid\MissingIconInformation;
use IndifferentKetchup\CodexPz\Analysis\ProjectZomboid\MissingThumpSoundInformation;
use IndifferentKetchup\CodexPz\Analysis\ProjectZomboid\SpriteConfigInvalidInformation;
use IndifferentKetchup\CodexPz\Analysis\Severity;
use IndifferentKetchup\CodexPz\Pattern\ProjectZomboid\AssetWarningPattern;
use PHPUnit\Framework\TestCase;

class AssetWarningTest extends TestCase
{
    // ── SpriteConfigInvalidInformation ────────────────────────────────────────

    public function testSpriteConfigPatternMatchesRealShape(): void
    {
        $line = '[16-04-26 12:34:56.789] WARN : General      f:0, t:1234567890123, st:1,2,3,4> SpriteConfig.initObjectInfo> Invalid SpriteConfig object! scripted object = Wooden_Windows.';
        $this->assertSame(1, preg_match(AssetWarningPattern::SPRITE_CONFIG_INVALID, $line, $m));
        $this->assertSame('Wooden_Windows', $m['object']);
    }

    public function testSpriteConfigExtractsObjectNameAndSeverity(): void
    {
        $info = new SpriteConfigInvalidInformation();
        $info->setMatches(['object' => 'Wooden_Windows'], 0);

        $this->assertSame('Invalid sprite config', $info->getLabel());
        $this->assertSame('Wooden_Windows', $info->getValue());
        $this->assertSame(Severity::Low, $info->getSeverity());
    }

    public function testSpriteConfigIsNotEngineNoise(): void
    {
        $this->assertNotInstanceOf(EngineNoiseInsightInterface::class, new SpriteConfigInvalidInformation());
    }

    public function testSpriteConfigCoalescesBySameObjectName(): void
    {
        $a = new SpriteConfigInvalidInformation();
        $a->setMatches(['object' => 'Wooden_Windows'], 0);

        $b = new SpriteConfigInvalidInformation();
        $b->setMatches(['object' => 'Wooden_Windows'], 0);

        $c = new SpriteConfigInvalidInformation();
        $c->setMatches(['object' => 'WoodFloorLvl3'], 0);

        $this->assertTrue($a->isEqual($b));
        $this->assertFalse($a->isEqual($c));
    }

    // ── MissingIconInformation ────────────────────────────────────────────────

    public function testMissingIconPatternMatchesRealShape(): void
    {
        $line = '[16-04-26 12:34:56.789] WARN : General      f:0, t:1234567890123, st:1,2,3,4> at XuiSkin$EntityUiStyle.LoadComponentInfo> Could not find icon: Item_Dice.';
        $this->assertSame(1, preg_match(AssetWarningPattern::MISSING_ICON, $line, $m));
        $this->assertSame('Item_Dice', $m['icon']);
    }

    public function testMissingIconExtractsIconNameAndSeverity(): void
    {
        $info = new MissingIconInformation();
        $info->setMatches(['icon' => 'Build_TableLargeWood'], 0);

        $this->assertSame('Missing icon', $info->getLabel());
        $this->assertSame('Build_TableLargeWood', $info->getValue());
        $this->assertSame(Severity::Noise, $info->getSeverity());
    }

    public function testMissingIconImplementsEngineNoise(): void
    {
        $this->assertInstanceOf(EngineNoiseInsightInterface::class, new MissingIconInformation());
    }

    public function testMissingIconCoalescesBySameIconName(): void
    {
        $a = new MissingIconInformation();
        $a->setMatches(['icon' => 'Item_Dice'], 0);

        $b = new MissingIconInformation();
        $b->setMatches(['icon' => 'Item_Dice'], 0);

        $c = new MissingIconInformation();
        $c->setMatches(['icon' => 'Item_Anvil_Forged'], 0);

        $this->assertTrue($a->isEqual($b));
        $this->assertFalse($a->isEqual($c));
    }

    // ── MissingThumpSoundInformation ──────────────────────────────────────────

    public function testThumpSoundPatternMatchesRealShape(): void
    {
        $line = '[16-04-26 12:34:56.789] ERROR: Sound        f:0, t:1234567890123, st:1,2,3,4> at BrokenFences.addBrokenTiles> Missing ThumpSound for breakable object fencing_01_9.';
        $this->assertSame(1, preg_match(AssetWarningPattern::MISSING_THUMPSOUND, $line, $m));
        $this->assertSame('fencing_01_9', $m['tile']);
    }

    public function testThumpSoundExtractsTileIdAndSeverity(): void
    {
        $info = new MissingThumpSoundInformation();
        $info->setMatches(['tile' => 'fencing_01_1'], 0);

        $this->assertSame('Missing ThumpSound', $info->getLabel());
        $this->assertSame('fencing_01_1', $info->getValue());
        $this->assertSame(Severity::Noise, $info->getSeverity());
    }

    public function testThumpSoundImplementsEngineNoise(): void
    {
        $this->assertInstanceOf(EngineNoiseInsightInterface::class, new MissingThumpSoundInformation());
    }

    public function testThumpSoundCoalescesBySameTileId(): void
    {
        $a = new MissingThumpSoundInformation();
        $a->setMatches(['tile' => 'fencing_01_1'], 0);

        $b = new MissingThumpSoundInformation();
        $b->setMatches(['tile' => 'fencing_01_1'], 0);

        $c = new MissingThumpSoundInformation();
        $c->setMatches(['tile' => 'fencing_01_9'], 0);

        $this->assertTrue($a->isEqual($b));
        $this->assertFalse($a->isEqual($c));
    }

    // ── BufferOverflowInformation ─────────────────────────────────────────────

    public function testBufferOverflowPatternMatchesRealShape(): void
    {
        $line = '[16-04-26 12:34:56.789] LOG  : General      f:0, t:1234567890123, st:1,2,3,4> IsoChunk.Save: BufferOverflowException, growing ByteBuffer.';
        $this->assertSame(1, preg_match(AssetWarningPattern::BUFFER_OVERFLOW, $line));
    }

    public function testBufferOverflowSeverityIsNoise(): void
    {
        $info = new BufferOverflowInformation();
        $info->setMatches([], 0);

        $this->assertSame(Severity::Noise, $info->getSeverity());
    }

    public function testBufferOverflowImplementsEngineNoise(): void
    {
        $this->assertInstanceOf(EngineNoiseInsightInterface::class, new BufferOverflowInformation());
    }

    public function testBufferOverflowAllInstancesCoalesce(): void
    {
        $a = new BufferOverflowInformation();
        $a->setMatches([], 0);

        $b = new BufferOverflowInformation();
        $b->setMatches([], 0);

        $this->assertTrue($a->isEqual($b));
    }
}
