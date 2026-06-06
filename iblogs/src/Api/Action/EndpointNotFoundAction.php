<?php

namespace IndifferentKetchup\Iblogs\Api\Action;

use IndifferentKetchup\Iblogs\Api\Response\ApiError;
use IndifferentKetchup\Iblogs\Api\Response\ApiResponse;

class EndpointNotFoundAction extends ApiAction
{
    protected function runApi(): ApiResponse
    {
        return new ApiError(404, "Could not find endpoint.");
    }
}