<?php

namespace IndifferentKetchup\Iblogs\Api\Action;

use IndifferentKetchup\Iblogs\Router\Action;

class EmptyAction extends Action
{
    public function run(): bool
    {
        return true;
    }
}