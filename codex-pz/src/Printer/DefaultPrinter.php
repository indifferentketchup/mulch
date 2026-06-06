<?php

namespace IndifferentKetchup\CodexPz\Printer;

use IndifferentKetchup\CodexPz\Log\LineInterface;

/**
 * Class DefaultPrinter
 *
 * @package IndifferentKetchup\CodexPz\Printer
 */
class DefaultPrinter extends Printer
{
    /**
     * Print a line
     *
     * @param LineInterface $line
     * @return string
     */
    protected function printLine(LineInterface $line): string
    {
        return $line->getText() . PHP_EOL;
    }
}