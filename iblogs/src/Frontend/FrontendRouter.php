<?php

namespace IndifferentKetchup\Iblogs\Frontend;

use IndifferentKetchup\Iblogs\Router\Router;
use IndifferentKetchup\Iblogs\Id;
use IndifferentKetchup\Iblogs\Router\Method;

class FrontendRouter extends Router
{
    protected function __construct()
    {
        parent::__construct();
        $this->register(Method::GET, "#^/$#", new Action\StartAction())
            ->register(Method::GET, "#^/" . Id::PATTERN . "$#", new Action\ViewLogAction())
            ->register(Method::POST, "#^/new$#", new Action\CreateLogAction())
            ->register(Method::DELETE, "#^/" . Id::PATTERN . "$#", new Action\DeleteLogAction())
            ->register(Method::GET, "#^/favicon\.svg$#", new Action\FaviconAction())
            ->setDefaultAction(new Action\NotFoundAction());
    }
}