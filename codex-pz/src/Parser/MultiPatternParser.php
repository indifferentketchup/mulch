<?php

namespace IndifferentKetchup\CodexPz\Parser;

use IndifferentKetchup\CodexPz\Log\Entry;
use IndifferentKetchup\CodexPz\Log\Line;
use InvalidArgumentException;

/**
 * A PatternParser variant that tries multiple line formats in registration order,
 * taking the first match. Lines that match no format are treated as continuations
 * of the most-recent entry (identical to PatternParser's behaviour for unmatched
 * lines), including the edge case where no entry exists yet at the top of the file.
 */
class MultiPatternParser extends PatternParser
{
    protected array $lineFormats = [];

    /**
     * Register an additional line format. Formats are tried in registration order;
     * the first match wins.
     *
     * @param string   $regex      PCRE regex with unnamed capture groups
     * @param array    $matchTypes PatternParser::TIME / LEVEL / PREFIX constants in capture-group order
     */
    public function addLineFormat(string $regex, array $matchTypes): static
    {
        $this->lineFormats[] = [$regex, $matchTypes];
        return $this;
    }

    public function parse(): void
    {
        foreach ($this->getLogContentAsArray() as $number => $lineString) {
            $line = new Line($number + 1, $lineString);

            $matched = false;
            foreach ($this->lineFormats as [$regex, $matchTypes]) {
                $result = preg_match($regex, $lineString, $matches);
                if ($result !== 1) {
                    continue;
                }

                /** @var Entry $entry */
                $entry = new $this->entryClass();
                $this->log->addEntry($entry);
                foreach ($matches as $key => $match) {
                    if ($key === 0) {
                        continue;
                    }
                    $matchKey = $key - 1;
                    if (!isset($matchTypes[$matchKey])) {
                        throw new InvalidArgumentException("More matches found in string than defined in MultiPatternParser::addLineFormat().");
                    }
                    $this->parseEntryMatch($entry, $matchTypes[$matchKey], $match);
                }
                $entry->addLine($line);
                $matched = true;
                break;
            }

            if (!$matched) {
                if (!isset($entry)) {
                    /** @var Entry $entry */
                    $entry = new $this->entryClass();
                    $this->log->addEntry($entry);
                }
                $entry->addLine($line);
            }
        }
    }
}
