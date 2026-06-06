<?php

namespace IndifferentKetchup\CodexPz\Log\ProjectZomboid;

use IndifferentKetchup\CodexPz\Analyser\AnalyserInterface;
use IndifferentKetchup\CodexPz\Analyser\CompositeAnalyser;
use IndifferentKetchup\CodexPz\Analyser\ProjectZomboid\StackTraceClassificationAnalyser;
use IndifferentKetchup\CodexPz\Analyser\ProjectZomboid\WarningPatternAnalyser;
use IndifferentKetchup\CodexPz\Analysis\ProjectZomboid\AnimClipNotFoundInformation;
use IndifferentKetchup\CodexPz\Analysis\ProjectZomboid\BoneIndexMissingProblem;
use IndifferentKetchup\CodexPz\Analysis\ProjectZomboid\BufferOverflowInformation;
use IndifferentKetchup\CodexPz\Analysis\ProjectZomboid\EngineVersionInformation;
use IndifferentKetchup\CodexPz\Analysis\ProjectZomboid\FunctionMissingProblem;
use IndifferentKetchup\CodexPz\Analysis\ProjectZomboid\MissingIconInformation;
use IndifferentKetchup\CodexPz\Analysis\ProjectZomboid\MissingThumpSoundInformation;
use IndifferentKetchup\CodexPz\Analysis\ProjectZomboid\ModLoadInformation;
use IndifferentKetchup\CodexPz\Analysis\ProjectZomboid\ModMissingProblem;
use IndifferentKetchup\CodexPz\Analysis\ProjectZomboid\RecursiveRequireProblem;
use IndifferentKetchup\CodexPz\Analysis\ProjectZomboid\RequireFailedProblem;
use IndifferentKetchup\CodexPz\Analysis\ProjectZomboid\SpriteConfigInvalidInformation;
use IndifferentKetchup\CodexPz\Analysis\ProjectZomboid\UnknownItemParamInformation;
use IndifferentKetchup\CodexPz\Analysis\ProjectZomboid\UnknownSandboxOptionInformation;
use IndifferentKetchup\CodexPz\Detective\FilenameDetector;
use IndifferentKetchup\CodexPz\Detective\WeightedSinglePatternDetector;
use IndifferentKetchup\CodexPz\Parser\ParserInterface;
use IndifferentKetchup\CodexPz\Parser\PatternParser;
use IndifferentKetchup\CodexPz\Pattern\ProjectZomboid\DebugServerPattern;

/**
 * Project Zomboid engine debug log (DebugLog-server.txt).
 *
 * Multi-line format: ERROR entries are followed by tab-indented stack trace
 * frames. PatternParser handles continuation by appending non-matching lines
 * to the most recent Entry, which is exactly the behaviour we need.
 */
class ProjectZomboidServerLog extends ProjectZomboidLog
{
    public static function getDefaultParser(): ParserInterface
    {
        return static::makeMultiPatternParser([
            [DebugServerPattern::LINE_B41_B42, [PatternParser::TIME, PatternParser::LEVEL, PatternParser::PREFIX]],
            [DebugServerPattern::LINE_B4X, [PatternParser::TIME, PatternParser::LEVEL, PatternParser::PREFIX]],
        ]);
    }

    public static function getDefaultAnalyser(): AnalyserInterface
    {
        // PatternAnalyser owns single-line families only. ServerExceptionProblem
        // is intentionally absent — StackTraceClassificationAnalyser is the sole
        // producer for "Exception thrown"-shaped entries (one-producer seam,
        // WARN-004/T2). Registering it here would double-count mod exceptions.
        $patternAnalyser = (new WarningPatternAnalyser())
            ->addPossibleInsightClass(EngineVersionInformation::class)
            ->addPossibleInsightClass(ModLoadInformation::class)
            ->addPossibleInsightClass(ModMissingProblem::class)
            ->addPossibleInsightClass(RequireFailedProblem::class)
            ->addPossibleInsightClass(FunctionMissingProblem::class)
            ->addPossibleInsightClass(RecursiveRequireProblem::class)
            ->addPossibleInsightClass(BoneIndexMissingProblem::class)
            ->addPossibleInsightClass(AnimClipNotFoundInformation::class)
            ->addPossibleInsightClass(SpriteConfigInvalidInformation::class)
            ->addPossibleInsightClass(MissingIconInformation::class)
            ->addPossibleInsightClass(MissingThumpSoundInformation::class)
            ->addPossibleInsightClass(BufferOverflowInformation::class)
            ->addPossibleInsightClass(UnknownSandboxOptionInformation::class)
            ->addPossibleInsightClass(UnknownItemParamInformation::class);

        return new CompositeAnalyser($patternAnalyser, new StackTraceClassificationAnalyser());
    }

    public static function getDetectors(): array
    {
        return [
            (new FilenameDetector())
                ->setPattern('/DebugLog-server\.txt$/')
                ->setWeight(0.95),
            (new WeightedSinglePatternDetector())
                ->setPattern('/version=\d+\.\d+\.\d+ [a-f0-9]{40}/')
                ->setWeight(0.95),
            (new WeightedSinglePatternDetector())
                ->setPattern('/^\[\d{2}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\.\d{3}\] (?:LOG|WARN|ERROR):\s+\w+\s+f:\d+, t:\d+, st:[\d,]+>/m')
                ->setWeight(0.80),
        ];
    }

    public function getTitle(): string
    {
        return "Project Zomboid Debug Server Log";
    }
}
