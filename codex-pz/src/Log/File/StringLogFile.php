<?php

namespace IndifferentKetchup\CodexPz\Log\File;

/**
 * Class StringLogFile
 *
 * @package IndifferentKetchup\CodexPz\Log\File
 */
class StringLogFile extends LogFile
{
    /**
     * StringLogFile constructor.
     *
     * @param string $string
     */
    public function __construct(string $string)
    {
        $this->content = $string;
    }
}