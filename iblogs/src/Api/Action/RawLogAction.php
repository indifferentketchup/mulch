<?php

namespace IndifferentKetchup\Iblogs\Api\Action;

use IndifferentKetchup\Iblogs\Api\Response\ApiError;
use IndifferentKetchup\Iblogs\Api\Response\ApiResponse;
use IndifferentKetchup\Iblogs\Api\Response\RawLogResponse;
use IndifferentKetchup\Iblogs\Id;
use IndifferentKetchup\Iblogs\Log;
use IndifferentKetchup\Iblogs\Util\URL;

class RawLogAction extends ApiAction
{
    /**
     * @return ApiResponse
     */
    protected function runApi(): ApiResponse
    {
        $id = new Id(URL::getLastPathPart());
        $log = Log::find($id);

        if (!$log) {
            return new ApiError(404, "Log not found.");
        }

        return new RawLogResponse($log);
    }
}