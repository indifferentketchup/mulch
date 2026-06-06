<?php

namespace IndifferentKetchup\CodexPz\Parser;

use IndifferentKetchup\CodexPz\Log\Entry;
use IndifferentKetchup\CodexPz\Log\Line;

/**
 * Class DefaultParser
 *
 * @package IndifferentKetchup\CodexPz\Parser
 */
class DefaultParser extends Parser
{
    /**
     * Parse a log from resource to Log object
     */
    public function parse(): void
    {
        foreach ($this->getLogContentAsArray() as $number => $logLineString) {
            $this->log->addEntry((new Entry())
                ->addLine(new Line($number + 1, $logLineString))
            );
        }
    }
}