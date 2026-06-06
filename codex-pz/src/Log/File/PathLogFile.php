<?php

namespace IndifferentKetchup\CodexPz\Log\File;

use InvalidArgumentException;

/**
 * Class PathLogFile
 *
 * @package IndifferentKetchup\CodexPz\Log\File
 */
class PathLogFile extends LogFile
{
    /**
     * PathLogFile constructor.
     *
     * @param string $path
     */
    public function __construct(string $path)
    {
        if (!file_exists($path)) {
            throw new InvalidArgumentException("File '" . $path . "' not found.");
        }

        $this->path = $path;
        $this->content = file_get_contents($path);
    }
}