<?php

namespace IndifferentKetchup\Iblogs\Frontend\Action;

use IndifferentKetchup\Iblogs\Router\Action;

class ApiDocsAction extends Action
{
    public function run(): bool
    {
        require __DIR__ . "/../../../web/frontend/api-docs.php";
        return true;
    }
}