<?php

namespace IndifferentKetchup\CodexPz\Test\Tests\Games\SevenDaysToDie;

use IndifferentKetchup\CodexPz\Detective\SevenDaysToDie\SevenDaysToDieDetective;
use PHPUnit\Framework\TestCase;

class SmokeTest extends TestCase
{
    public function testDetectiveIsInstantiable(): void
    {
        $detective = new SevenDaysToDieDetective();
        $this->assertInstanceOf(SevenDaysToDieDetective::class, $detective);
    }
}
