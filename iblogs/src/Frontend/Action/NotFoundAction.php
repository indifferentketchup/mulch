<?php

namespace IndifferentKetchup\Iblogs\Frontend\Action;

use IndifferentKetchup\Iblogs\Router\Action;

class NotFoundAction extends Action
{
    public function run(): bool
    {
        http_response_code(404);
        require __DIR__ . "/../../../web/frontend/404.php";
        return true;
    }
}