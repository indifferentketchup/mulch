<?php

namespace IndifferentKetchup\CodexPz\Detective;

use IndifferentKetchup\CodexPz\Log\File\LogFileInterface;

/**
 * Class Detector
 *
 * @package IndifferentKetchup\CodexPz\Detective
 */
abstract class Detector implements DetectorInterface
{
    protected ?LogFileInterface $logFile = null;

    /**
     * Set the log file
     *
     * @param LogFileInterface $logFile
     * @return $this
     */
    public function setLogFile(LogFileInterface $logFile): static
    {
        $this->logFile = $logFile;
        return $this;
    }

    /**
     * Get the log content as string
     *
     * @return string
     */
    protected function getLogContent(): string
    {
        return $this->logFile->getContent();
    }

    /**
     * Get the log content as array split by line
     *
     * @return string[]
     */
    protected function getLogContentAsArray(): array
    {
        return explode(PHP_EOL, $this->getLogContent());
    }
}