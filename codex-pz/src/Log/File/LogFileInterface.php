<?php

namespace IndifferentKetchup\CodexPz\Log\File;

/**
 * Interface LogFileInterface
 *
 * @package IndifferentKetchup\CodexPz\Log\File
 */
interface LogFileInterface
{
    /**
     * Get the log file content
     *
     * @return string
     */
    public function getContent(): string;

    /**
     * Get the source path of the log file when one is known
     *
     * Returns null for log files without a filesystem origin (string content,
     * arbitrary streams). Concrete implementations should return the path used
     * to construct them when applicable.
     *
     * @return string|null
     */
    public function getPath(): ?string;
}