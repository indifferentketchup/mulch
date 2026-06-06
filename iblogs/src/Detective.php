<?php

namespace IndifferentKetchup\Iblogs;

use IndifferentKetchup\CodexPz\Detective\Hytale\HytaleDetective;
use IndifferentKetchup\CodexPz\Detective\Minecraft\MinecraftDetective;
use IndifferentKetchup\CodexPz\Detective\ProjectZomboid\ProjectZomboidDetective;

class Detective extends \IndifferentKetchup\CodexPz\Detective\Detective
{
    public function __construct()
    {
        $this->addDetective(new MinecraftDetective())
            ->addDetective(new HytaleDetective())
            ->addDetective(new ProjectZomboidDetective());
    }
}
