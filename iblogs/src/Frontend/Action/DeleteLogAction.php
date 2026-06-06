<?php

namespace IndifferentKetchup\Iblogs\Frontend\Action;

use IndifferentKetchup\Iblogs\Frontend\Cookie\TokenCookie;
use IndifferentKetchup\Iblogs\Log;
use IndifferentKetchup\Iblogs\Util\URL;

class DeleteLogAction extends \IndifferentKetchup\Iblogs\Api\Action\DeleteLogAction
{
    protected function getAllowedOrigin(): string
    {
        return URL::getBase()->toString();
    }

    protected function shouldAllowCredentials(): bool
    {
        return true;
    }

    protected function getRequestToken(): ?string
    {
        return new TokenCookie()->getValue();
    }

    protected function handleDeletedLog(Log $log): void
    {
        new TokenCookie()->setLog($log)->delete();
    }
}