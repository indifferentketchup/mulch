<?php

namespace IndifferentKetchup\Iblogs\Api\Action;

use IndifferentKetchup\Iblogs\Api\Response\ApiResponse;
use IndifferentKetchup\Iblogs\Api\Response\LimitsResponse;

class GetLimitsAction extends ApiAction
{
    protected function runApi(): ApiResponse
    {
        return new LimitsResponse();
    }
}