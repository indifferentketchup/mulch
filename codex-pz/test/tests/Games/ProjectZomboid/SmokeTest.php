<?php

namespace IndifferentKetchup\CodexPz\Test\Tests\Games\ProjectZomboid;

use IndifferentKetchup\CodexPz\Detective\ProjectZomboid\ProjectZomboidDetective;
use PHPUnit\Framework\TestCase;

class SmokeTest extends TestCase
{
    public function testDetectiveIsInstantiable(): void
    {
        $detective = new ProjectZomboidDetective();
        $this->assertInstanceOf(ProjectZomboidDetective::class, $detective);
    }
}
