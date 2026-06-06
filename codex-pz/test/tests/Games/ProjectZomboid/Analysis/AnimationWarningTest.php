<?php

namespace IndifferentKetchup\CodexPz\Test\Tests\Games\ProjectZomboid\Analysis;

use IndifferentKetchup\CodexPz\Analysis\ProjectZomboid\AnimClipNotFoundInformation;
use IndifferentKetchup\CodexPz\Analysis\ProjectZomboid\BoneIndexMissingProblem;
use IndifferentKetchup\CodexPz\Analysis\Severity;
use IndifferentKetchup\CodexPz\Pattern\ProjectZomboid\AnimationWarningPattern;
use PHPUnit\Framework\TestCase;

class AnimationWarningTest extends TestCase
{
    // ── AnimClipNotFoundInformation ───────────────────────────────────────────

    public function testAnimClipPatternMatchesRealShape(): void
    {
        $line = '[16-04-26 12:34:56.789] WARN : General      f:0, t:1234567890123, st:1,2,3,4> AnimationPlayer.play> Anim Clip not found: InvalidOnPurpose.';
        $this->assertSame(1, preg_match(AnimationWarningPattern::ANIM_CLIP_NOT_FOUND, $line, $m));
        $this->assertSame('InvalidOnPurpose', $m['clip']);
    }

    public function testAnimClipPatternMatchesAtPrefixVariant(): void
    {
        $line = '[16-04-26 12:34:56.789] WARN : Animation     f:0, t:1234567890123, st:1,2,3,4> at AnimationPlayer.play> Anim Clip not found: Bob_SneakToSneakRun2H_Heavy.';
        $this->assertSame(1, preg_match(AnimationWarningPattern::ANIM_CLIP_NOT_FOUND, $line, $m));
        $this->assertSame('Bob_SneakToSneakRun2H_Heavy', $m['clip']);
    }

    public function testAnimClipExtractsClipNameAndSeverity(): void
    {
        $info = new AnimClipNotFoundInformation();
        $info->setMatches(['clip' => 'InvalidOnPurpose'], 0);

        $this->assertSame('Anim clip not found', $info->getLabel());
        $this->assertSame('InvalidOnPurpose', $info->getValue());
        $this->assertSame(Severity::Low, $info->getSeverity());
    }

    public function testAnimClipCoalescesBySameClipName(): void
    {
        $a = new AnimClipNotFoundInformation();
        $a->setMatches(['clip' => 'InvalidOnPurpose'], 0);

        $b = new AnimClipNotFoundInformation();
        $b->setMatches(['clip' => 'InvalidOnPurpose'], 0);

        $c = new AnimClipNotFoundInformation();
        $c->setMatches(['clip' => 'turning180'], 0);

        $this->assertTrue($a->isEqual($b));
        $this->assertFalse($a->isEqual($c));
    }

    // ── BoneIndexMissingProblem ───────────────────────────────────────────────

    public function testBoneIndexPatternMatchesRealShape(): void
    {
        $line = '[16-04-26 12:34:56.789] ERROR: General      f:0, t:1234567890123, st:1,2,3,4> ImportedSkeleton.collectBoneFrames> Could not find bone index for node name: "Body".';
        $this->assertSame(1, preg_match(AnimationWarningPattern::BONE_INDEX_MISSING, $line, $m));
        $this->assertSame('Body', $m['node']);
    }

    public function testBoneIndexExtractsNodeNameAndSeverity(): void
    {
        $problem = new BoneIndexMissingProblem();
        $problem->setMatches(['node' => 'Dummy01IK'], 0);

        $this->assertSame('Dummy01IK', $problem->getNodeName());
        $this->assertStringContainsString('Dummy01IK', $problem->getMessage());
        $this->assertSame(Severity::Medium, $problem->getSeverity());
    }

    public function testBoneIndexCoalescesBySameNodeName(): void
    {
        $a = new BoneIndexMissingProblem();
        $a->setMatches(['node' => 'Body'], 0);

        $b = new BoneIndexMissingProblem();
        $b->setMatches(['node' => 'Body'], 0);

        $c = new BoneIndexMissingProblem();
        $c->setMatches(['node' => 'Cube'], 0);

        $this->assertTrue($a->isEqual($b));
        $this->assertFalse($a->isEqual($c));
    }
}
