<?php

namespace IndifferentKetchup\CodexPz\Test\Tests\Games\Hytale;

use IndifferentKetchup\CodexPz\Detective\Hytale\HytaleDetective;
use PHPUnit\Framework\TestCase;

class SmokeTest extends TestCase
{
    public function testDetectiveIsInstantiable(): void
    {
        $detective = new HytaleDetective();
        $this->assertInstanceOf(HytaleDetective::class, $detective);
    }
}
