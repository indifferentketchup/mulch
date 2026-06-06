<?php

namespace IndifferentKetchup\CodexPz\Test\Tests\Games\ProjectZomboid\Analysis;

use IndifferentKetchup\CodexPz\Pattern\ProjectZomboid\AnimationWarningPattern;
use IndifferentKetchup\CodexPz\Pattern\ProjectZomboid\AssetWarningPattern;
use IndifferentKetchup\CodexPz\Pattern\ProjectZomboid\ConfigDriftPattern;
use IndifferentKetchup\CodexPz\Pattern\ProjectZomboid\LuaWarningPattern;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Verifies the ONE-PRODUCER SEAM: none of the 11 Phase-4 patterns fire on an
 * "Exception thrown" entry. StackTraceClassificationAnalyser (Phase 5) owns
 * those exclusively.
 */
class ExceptionSeamTest extends TestCase
{
    private string $exceptionEntry;

    protected function setUp(): void
    {
        // Synthetic "Exception thrown" entry (B41 format with tab-indented stack)
        $this->exceptionEntry = implode("\n", [
            '[01-01-25 00:00:00.000] ERROR: General      f:0, t:1234567890000, st:1,2,3,4> Foo.bar> Exception thrown',
            "\tjava.lang.RuntimeException: something went wrong at Foo.bar(Foo.java:42).",
            "\tStack trace:",
            "\t\tat zombie.Foo.bar(Foo.java:42)",
            "\t\tat zombie.Server.main(Server.java:1)",
        ]);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function allPhase4Patterns(): array
    {
        return [
            'REQUIRE_FAILED'         => [LuaWarningPattern::REQUIRE_FAILED],
            'FUNCTION_MISSING'       => [LuaWarningPattern::FUNCTION_MISSING],
            'RECURSIVE_REQUIRE'      => [LuaWarningPattern::RECURSIVE_REQUIRE],
            'ANIM_CLIP_NOT_FOUND'    => [AnimationWarningPattern::ANIM_CLIP_NOT_FOUND],
            'BONE_INDEX_MISSING'     => [AnimationWarningPattern::BONE_INDEX_MISSING],
            'SPRITE_CONFIG_INVALID'  => [AssetWarningPattern::SPRITE_CONFIG_INVALID],
            'MISSING_ICON'           => [AssetWarningPattern::MISSING_ICON],
            'MISSING_THUMPSOUND'     => [AssetWarningPattern::MISSING_THUMPSOUND],
            'BUFFER_OVERFLOW'        => [AssetWarningPattern::BUFFER_OVERFLOW],
            'UNKNOWN_SANDBOX_OPTION' => [ConfigDriftPattern::UNKNOWN_SANDBOX_OPTION],
            'UNKNOWN_ITEM_PARAM'     => [ConfigDriftPattern::UNKNOWN_ITEM_PARAM],
        ];
    }

    #[DataProvider('allPhase4Patterns')]
    public function testPhase4PatternDoesNotMatchExceptionThrownEntry(string $pattern): void
    {
        $this->assertSame(
            0,
            preg_match($pattern, $this->exceptionEntry),
            "Pattern $pattern must NOT match an 'Exception thrown' entry (ONE-PRODUCER SEAM violation)"
        );
    }

    public function testExceptionEntryContainsExceptionThrown(): void
    {
        // Sanity check: our test entry is actually exception-shaped
        $this->assertStringContainsString('Exception thrown', $this->exceptionEntry);
    }
}
