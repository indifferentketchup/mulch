<?php

namespace IndifferentKetchup\Iblogs\Frontend\Action;

use IndifferentKetchup\Iblogs\Id;
use IndifferentKetchup\Iblogs\Log;
use IndifferentKetchup\Iblogs\Router\Action;
use IndifferentKetchup\Iblogs\Util\URL;

class ViewLogAction extends Action
{
    public function run(): bool
    {
        $id = new Id(URL::getLastPathPart());
        $log = Log::find($id);
        if (!$log) {
            return false;
        }

        $log->renew();

        require __DIR__ . "/../../../web/frontend/log.php";
        return true;
    }
}