<?php

namespace IndifferentKetchup\CodexPz\Test\Tests\Util\ProjectZomboid;

use IndifferentKetchup\CodexPz\Util\ProjectZomboid\ProjectZomboidModAttributor;
use PHPUnit\Framework\TestCase;

class ProjectZomboidModAttributorTest extends TestCase
{
    public function testKnownModGetsWorkshopId(): void
    {
        $input = 'Lua((MOD:ImmersiveSolarArrays)).foo(bar.lua:12)';
        $expected = 'Lua((MOD:<span class="mod-attribution" data-workshop-id="2857548524">ImmersiveSolarArrays</span>)).foo(bar.lua:12)';

        $output = (new ProjectZomboidModAttributor())->modify($input);

        $this->assertSame($expected, $output);
    }

    public function testUnknownModGetsSpanWithoutWorkshopId(): void
    {
        $input = 'Lua((MOD:NeverSeenBefore)).foo(bar.lua:42)';
        $expected = 'Lua((MOD:<span class="mod-attribution">NeverSeenBefore</span>)).foo(bar.lua:42)';

        $output = (new ProjectZomboidModAttributor())->modify($input);

        $this->assertSame($expected, $output);
    }

    public function testMultipleFramesInOneInput(): void
    {
        $input = "Lua((MOD:ImmersiveSolarArrays)).foo(a.lua:1)\n"
            . "Lua((MOD:WaterGoesBad)).bar(b.lua:2)";

        $output = (new ProjectZomboidModAttributor())->modify($input);

        $this->assertStringContainsString(
            '<span class="mod-attribution" data-workshop-id="2857548524">ImmersiveSolarArrays</span>',
            $output,
            'First known mod should be decorated with its workshop id.'
        );
        $this->assertStringContainsString(
            '<span class="mod-attribution" data-workshop-id="2849467715">WaterGoesBad</span>',
            $output,
            'Second known mod should be decorated with its workshop id.'
        );
    }

    public function testPlainTextWithoutModTokenPasses(): void
    {
        $input = 'plain log line, nothing here';

        $output = (new ProjectZomboidModAttributor())->modify($input);

        $this->assertSame($input, $output, 'Input without a MOD: token must pass through unchanged.');
    }

    public function testIdempotent(): void
    {
        $attributor = new ProjectZomboidModAttributor();
        $input = 'Lua((MOD:ImmersiveSolarArrays)).foo(bar.lua:12) and Lua((MOD:NeverSeenBefore)).bar(baz.lua:99)';

        $once = $attributor->modify($input);
        $twice = $attributor->modify($once);

        $this->assertSame($once, $twice, 'A second modify() pass must leave the first pass\'s output unchanged.');
    }

    public function testHtmlSafeOnHostileModName(): void
    {
        $input = 'Lua((MOD:<script>alert(1)</script>)).foo(bar.lua:12)';

        $output = (new ProjectZomboidModAttributor())->modify($input);

        // The hostile name is unaltered if it begins with `<` (idempotence guard
        // excludes `<` from the captured run), so the input passes through
        // untouched. That is itself safe — the consumer must always HTML-escape
        // log content before rendering — but we explicitly assert the literal
        // raw `<script>` does NOT survive in any decorated form. The simplest
        // unambiguous assertion is "no `mod-attribution` span was synthesized
        // around the hostile name", which in turn means the dangerous payload
        // is not hidden inside our markup.
        $this->assertStringNotContainsString(
            '<span class="mod-attribution"><script>',
            $output,
            'Hostile mod name beginning with `<` must not appear unescaped inside a mod-attribution span.'
        );
    }

    public function testHtmlSafeOnHostileCharactersInsideName(): void
    {
        // A mod name containing `&` (a more realistic hostile-ish character that
        // does not begin with `<`) IS captured and must be HTML-escaped on the
        // way out.
        $input = 'Lua((MOD:Foo & "Bar"\'s Mod)).baz(q.lua:1)';

        $output = (new ProjectZomboidModAttributor())->modify($input);

        $this->assertStringContainsString(
            'Foo &amp; &quot;Bar&quot;&apos;s Mod',
            $output,
            'Special HTML characters in the mod name must be htmlspecialchars-escaped (ENT_HTML5 emits &apos; for `\'`).'
        );
        $this->assertStringNotContainsString(
            'Foo & "Bar"\'s Mod</span>',
            $output,
            'The raw unescaped form must not survive in the decorated output.'
        );
    }

    public function testApostropheModName(): void
    {
        // htmlspecialchars under ENT_QUOTES | ENT_HTML5 emits the named entity
        // `&apos;` for `'` (rather than the numeric `&#039;` of ENT_HTML401).
        $input = "Lua((MOD:Spongie's Clothing)).customGetVal(SpongieCopy_BodyLocationsTweaker.lua:12)";
        $expected = 'Lua((MOD:<span class="mod-attribution">Spongie&apos;s Clothing</span>)).customGetVal(SpongieCopy_BodyLocationsTweaker.lua:12)';

        $output = (new ProjectZomboidModAttributor())->modify($input);

        $this->assertSame($expected, $output);
    }

    public function testEnrichDelegatesToSameLogic(): void
    {
        $input = 'Lua((MOD:ImmersiveSolarArrays)).foo(bar.lua:12)';

        $attributor = new ProjectZomboidModAttributor();
        $viaModify = $attributor->modify($input);
        $viaEnrich = $attributor->enrich($input);

        $this->assertSame(
            $viaModify,
            $viaEnrich,
            'enrich() must produce byte-identical output to modify() for the same input.'
        );
    }

    public function testEmptyInputReturnsEmptyString(): void
    {
        $sut = new ProjectZomboidModAttributor();
        $this->assertSame('', $sut->modify(''));
    }
}
