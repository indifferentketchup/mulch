<?php

namespace IndifferentKetchup\Iblogs\Api\Action;

use IndifferentKetchup\Iblogs\Api\Response\ApiError;
use IndifferentKetchup\Iblogs\Api\Response\ApiResponse;

class RateLimitErrorAction extends ApiAction
{
    protected function runApi(): ApiResponse
    {
        return new ApiError(
            429,
            "Unfortunately you have exceeded the rate limit for the current time period. Please try again later."
        );
    }
}