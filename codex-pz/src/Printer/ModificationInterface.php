<?php

namespace IndifferentKetchup\CodexPz\Printer;

/**
 * Interface ModificationInterface
 *
 * @package IndifferentKetchup\CodexPz\Printer
 */
interface ModificationInterface
{
    /**
     * Modify the given string and return it
     *
     * @param string $text
     * @return string
     */
    public function modify(string $text): string;
}