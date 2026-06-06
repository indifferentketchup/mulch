<?php

namespace IndifferentKetchup\CodexPz\Printer;

use IndifferentKetchup\CodexPz\Log\EntryInterface;
use IndifferentKetchup\CodexPz\Log\LogInterface;

/**
 * Interface PrinterInterface
 *
 * @package IndifferentKetchup\CodexPz\Printer
 */
interface PrinterInterface
{
    /**
     * Set the log
     *
     * @param LogInterface $log
     * @return $this
     */
    public function setLog(LogInterface $log): static;

    /**
     * Set the entry
     *
     * @param EntryInterface $entry
     * @return $this
     */
    public function setEntry(EntryInterface $entry): static;

    /**
     * Print the log
     *
     * @return string
     */
    public function print(): string;
}