<?php

namespace IndifferentKetchup\CodexPz\Log\File;

/**
 * Class LogFile
 *
 * @package IndifferentKetchup\CodexPz\Log\File
 */
abstract class LogFile implements LogFileInterface
{
    protected ?string $content = null;
    protected ?string $path = null;

    /**
     * Get the log file content
     *
     * @return string
     */
    public function getContent(): string
    {
        return $this->content;
    }

    /**
     * Get the source path of the log file when one is known
     *
     * @return string|null
     */
    public function getPath(): ?string
    {
        return $this->path;
    }
}