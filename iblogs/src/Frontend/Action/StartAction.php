<?php

namespace IndifferentKetchup\Iblogs\Frontend\Action;

use IndifferentKetchup\Iblogs\Router\Action;

class StartAction extends Action
{
    public function run(): bool
    {
        require __DIR__ . "/../../../web/frontend/start.php";
        return true;
    }
}