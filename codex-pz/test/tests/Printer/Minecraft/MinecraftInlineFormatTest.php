<?php

namespace IndifferentKetchup\CodexPz\Test\Tests\Printer\Minecraft;

use IndifferentKetchup\CodexPz\Printer\Minecraft\MinecraftInlineFormat;
use PHPUnit\Framework\TestCase;

class MinecraftInlineFormatTest extends TestCase
{
    public function testTranslatesSingleColorCode(): void
    {
        $format = new MinecraftInlineFormat();
        $this->assertSame(
            '<span class="format-darkred">red text</span>',
            $format->modify("\e[0;31;22mred text")
        );
    }

    public function testTranslatesResetCode(): void
    {
        $format = new MinecraftInlineFormat();
        $this->assertSame(
            'reset<span class="format-reset"></span>',
            $format->modify("reset\e[m")
        );
    }

    public function testTranslatesMultipleNestedSpans(): void
    {
        $format = new MinecraftInlineFormat();
        $this->assertSame(
            '<span class="format-darkred">red<span class="format-gold">yellow</span></span>',
            $format->modify("\e[0;31;22mred\e[0;33;22myellow")
        );
    }

    public function testLeavesNonAnsiTextUnchanged(): void
    {
        $format = new MinecraftInlineFormat();
        $this->assertSame(
            'plain text no codes',
            $format->modify('plain text no codes')
        );
    }

    public function testTranslatesStyleCodes(): void
    {
        $format = new MinecraftInlineFormat();
        $this->assertSame(
            '<span class="format-bold">bold<span class="format-underline">underline</span></span>',
            $format->modify("\e[21mbold\e[4munderline")
        );
    }
}
