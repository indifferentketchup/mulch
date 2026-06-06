<?php

namespace IndifferentKetchup\Iblogs\Frontend\Action;

use IndifferentKetchup\Iblogs\Util\URL;

class CreateLogAction extends \IndifferentKetchup\Iblogs\Api\Action\CreateLogAction
{
    protected bool $includeCookie = true;
    protected bool $includeToken = false;

    protected function getAllowedOrigin(): string
    {
        return URL::getBase()->toString();
    }

    protected function shouldAllowCredentials(): bool
    {
        return true;
    }
}