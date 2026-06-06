<?php

namespace IndifferentKetchup\CodexPz\Printer;

use IndifferentKetchup\CodexPz\Log\LineInterface;

/**
 * Class ModifiableDefaultPrinter
 *
 * @package IndifferentKetchup\CodexPz\Printer
 */
class ModifiableDefaultPrinter extends ModifiablePrinter
{
    /**
     * Print a line
     *
     * @param LineInterface $line
     * @return string
     */
    protected function printLine(LineInterface $line): string
    {
        return $this->runModifications($line->getText()) . PHP_EOL;
    }
}