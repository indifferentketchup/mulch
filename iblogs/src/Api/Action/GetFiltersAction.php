<?php

namespace IndifferentKetchup\Iblogs\Api\Action;

use IndifferentKetchup\Iblogs\Api\Response\ApiResponse;
use IndifferentKetchup\Iblogs\Api\Response\FiltersResponse;

class GetFiltersAction extends ApiAction
{
    protected function runApi(): ApiResponse
    {
        return new FiltersResponse();
    }
}