<?php

namespace IndifferentKetchup\Iblogs\Api\Action;

use IndifferentKetchup\Iblogs\Api\LogContentParser;
use IndifferentKetchup\Iblogs\Api\Response\ApiError;
use IndifferentKetchup\Iblogs\Api\Response\ApiResponse;
use IndifferentKetchup\Iblogs\Api\Response\CodexLogResponse;
use IndifferentKetchup\Iblogs\Log;

class AnalyseLogAction extends ApiAction
{
    public function runApi(): ApiResponse
    {
        $data = new LogContentParser()->getContent();

        if ($data instanceof ApiError) {
            return $data;
        }

        $content = $data['content'];
        $log = new Log()->setContent($content);

        return new CodexLogResponse($log->getCodexLog());
    }
}
