<?php

namespace IndifferentKetchup\CodexPz\Test\Tests\Analysis\ProjectZomboid;

use IndifferentKetchup\CodexPz\Analysis\ProjectZomboid\AnimClipNotFoundInformation;
use IndifferentKetchup\CodexPz\Analysis\ProjectZomboid\BoneIndexMissingProblem;
use IndifferentKetchup\CodexPz\Analysis\ProjectZomboid\BufferOverflowInformation;
use IndifferentKetchup\CodexPz\Analysis\ProjectZomboid\ConnectionFailureProblem;
use IndifferentKetchup\CodexPz\Analysis\ProjectZomboid\EngineNoiseExceptionInformation;
use IndifferentKetchup\CodexPz\Analysis\ProjectZomboid\EngineVersionInformation;
use IndifferentKetchup\CodexPz\Analysis\ProjectZomboid\ErrorContextProblem;
use IndifferentKetchup\CodexPz\Analysis\ProjectZomboid\ErrorContextTruncatedInformation;
use IndifferentKetchup\CodexPz\Analysis\ProjectZomboid\FunctionMissingProblem;
use IndifferentKetchup\CodexPz\Analysis\ProjectZomboid\ItemDuplicationProblem;
use IndifferentKetchup\CodexPz\Analysis\ProjectZomboid\JavaExceptionProblem;
use IndifferentKetchup\CodexPz\Analysis\ProjectZomboid\LuaModRuntimeProblem;
use IndifferentKetchup\CodexPz\Analysis\ProjectZomboid\MissingIconInformation;
use IndifferentKetchup\CodexPz\Analysis\ProjectZomboid\MissingThumpSoundInformation;
use IndifferentKetchup\CodexPz\Analysis\ProjectZomboid\ModLoadInformation;
use IndifferentKetchup\CodexPz\Analysis\ProjectZomboid\ModMissingProblem;
use IndifferentKetchup\CodexPz\Analysis\ProjectZomboid\PvpDamageInformation;
use IndifferentKetchup\CodexPz\Analysis\ProjectZomboid\RecursiveRequireProblem;
use IndifferentKetchup\CodexPz\Analysis\ProjectZomboid\RequireFailedProblem;
use IndifferentKetchup\CodexPz\Analysis\ProjectZomboid\ServerExceptionProblem;
use IndifferentKetchup\CodexPz\Analysis\ProjectZomboid\SkillProgressionAnomalyProblem;
use IndifferentKetchup\CodexPz\Analysis\ProjectZomboid\SpriteConfigInvalidInformation;
use IndifferentKetchup\CodexPz\Analysis\ProjectZomboid\UnknownItemParamInformation;
use IndifferentKetchup\CodexPz\Analysis\ProjectZomboid\UnknownSandboxOptionInformation;
use IndifferentKetchup\CodexPz\Analysis\Severity;
use IndifferentKetchup\CodexPz\Analysis\SeverityAwareInsightInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class TierReassignmentTest extends TestCase
{
    public function testJavaExceptionProblemCriticalForOom(): void
    {
        $problem = (new JavaExceptionProblem())->setExceptionClass('java.lang.OutOfMemoryError');
        $this->assertSame(Severity::Critical, $problem->getSeverity());
    }

    public function testJavaExceptionProblemDefaultMedium(): void
    {
        $problem = (new JavaExceptionProblem())->setExceptionClass('java.lang.NullPointerException');
        $this->assertSame(Severity::Medium, $problem->getSeverity());
    }

    public function testServerExceptionProblemCritical(): void
    {
        $problem = new ServerExceptionProblem();
        $this->assertInstanceOf(SeverityAwareInsightInterface::class, $problem);
        $this->assertSame(Severity::Critical, $problem->getSeverity());
    }

    /** @return array<string, array{class-string}> */
    public static function lowTierClasses(): array
    {
        return [
            'ConnectionFailureProblem' => [ConnectionFailureProblem::class],
            'ErrorContextProblem' => [ErrorContextProblem::class],
            'ErrorContextTruncatedInformation' => [ErrorContextTruncatedInformation::class],
            'ItemDuplicationProblem' => [ItemDuplicationProblem::class],
            'SkillProgressionAnomalyProblem' => [SkillProgressionAnomalyProblem::class],
            'ModLoadInformation' => [ModLoadInformation::class],
            'PvpDamageInformation' => [PvpDamageInformation::class],
            'EngineVersionInformation' => [EngineVersionInformation::class],
            'SpriteConfigInvalidInformation' => [SpriteConfigInvalidInformation::class],
            'AnimClipNotFoundInformation' => [AnimClipNotFoundInformation::class],
            'UnknownSandboxOptionInformation' => [UnknownSandboxOptionInformation::class],
        ];
    }

    #[DataProvider('lowTierClasses')]
    public function testLowTierClasses(string $class): void
    {
        $instance = new $class();
        $this->assertInstanceOf(SeverityAwareInsightInterface::class, $instance);
        $this->assertSame(Severity::Low, $instance->getSeverity(), "$class should be Severity::Low");
    }

    /** @return array<string, array{class-string}> */
    public static function mediumTierClasses(): array
    {
        return [
            'RequireFailedProblem' => [RequireFailedProblem::class],
            'ModMissingProblem' => [ModMissingProblem::class],
            'BoneIndexMissingProblem' => [BoneIndexMissingProblem::class],
        ];
    }

    #[DataProvider('mediumTierClasses')]
    public function testMediumTierClasses(string $class): void
    {
        $instance = new $class();
        $this->assertInstanceOf(SeverityAwareInsightInterface::class, $instance);
        $this->assertSame(Severity::Medium, $instance->getSeverity(), "$class should be Severity::Medium");
    }

    /** @return array<string, array{class-string}> */
    public static function highTierClasses(): array
    {
        return [
            'LuaModRuntimeProblem' => [LuaModRuntimeProblem::class],
            'RecursiveRequireProblem' => [RecursiveRequireProblem::class],
            'FunctionMissingProblem' => [FunctionMissingProblem::class],
        ];
    }

    #[DataProvider('highTierClasses')]
    public function testHighTierClasses(string $class): void
    {
        $instance = new $class();
        $this->assertInstanceOf(SeverityAwareInsightInterface::class, $instance);
        $this->assertSame(Severity::High, $instance->getSeverity(), "$class should be Severity::High");
    }

    /** @return array<string, array{class-string}> */
    public static function noiseTierClasses(): array
    {
        return [
            'MissingIconInformation' => [MissingIconInformation::class],
            'MissingThumpSoundInformation' => [MissingThumpSoundInformation::class],
            'BufferOverflowInformation' => [BufferOverflowInformation::class],
            'UnknownItemParamInformation' => [UnknownItemParamInformation::class],
            'EngineNoiseExceptionInformation' => [EngineNoiseExceptionInformation::class],
        ];
    }

    #[DataProvider('noiseTierClasses')]
    public function testNoiseTierClasses(string $class): void
    {
        $instance = new $class();
        $this->assertInstanceOf(SeverityAwareInsightInterface::class, $instance);
        $this->assertSame(Severity::Noise, $instance->getSeverity(), "$class should be Severity::Noise");
    }
}
