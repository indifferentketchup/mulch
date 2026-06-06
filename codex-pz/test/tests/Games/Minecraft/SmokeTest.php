<?php

namespace IndifferentKetchup\CodexPz\Test\Tests\Games\Minecraft;

use IndifferentKetchup\CodexPz\Detective\Minecraft\MinecraftDetective;
use PHPUnit\Framework\TestCase;

class SmokeTest extends TestCase
{
    public function testDetectiveIsInstantiable(): void
    {
        $detective = new MinecraftDetective();
        $this->assertInstanceOf(MinecraftDetective::class, $detective);
    }
}
