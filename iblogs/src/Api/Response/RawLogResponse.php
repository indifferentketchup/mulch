<?php

namespace IndifferentKetchup\Iblogs\Api\Response;

use IndifferentKetchup\Iblogs\Log;

class RawLogResponse extends ApiResponse
{
    public function __construct(
        protected Log  $log)
    {
    }

    public function output(): static
    {
        header('Content-Type: text/plain');
        echo $this->log->getContent();

        return $this;
    }

}