<?php

namespace IndifferentKetchup\CodexPz\Detective;

/**
 * Detector that runs a regex against the first N lines of the log only.
 *
 * Useful for game-detection patterns that match a banner/header at the top
 * of a log file — large logs (multi-MB) don't need a full content scan to
 * decide which game produced them.
 *
 * @package IndifferentKetchup\CodexPz\Detective
 */
class FirstLinesPatternDetector extends PatternDetector
{
    public const int DEFAULT_LINE_COUNT = 50;

    protected int $lineCount = self::DEFAULT_LINE_COUNT;
    protected float $weight = 1.0;

    /**
     * Set the maximum number of leading lines to inspect.
     *
     * @param int $lineCount
     * @return $this
     */
    public function setLineCount(int $lineCount): static
    {
        $this->lineCount = $lineCount;
        return $this;
    }

    /**
     * Set the weight returned on a successful match.
     *
     * @param float $weight
     * @return $this
     */
    public function setWeight(float $weight): static
    {
        if ($weight < 0.0 || $weight > 1.0) {
            throw new \InvalidArgumentException(
                "Weight must be in range [0.0, 1.0], got {$weight}"
            );
        }
        $this->weight = $weight;
        return $this;
    }

    /**
     * Detect if the log matches.
     *
     * Returns the configured weight when the pattern matches any of the
     * first $lineCount lines of the log content; returns false otherwise.
     *
     * @return bool|float
     */
    public function detect(): bool|float
    {
        if ($this->pattern === null) {
            return false;
        }
        $lines = $this->getLogContentAsArray();
        $head = array_slice($lines, 0, $this->lineCount);
        foreach ($head as $line) {
            if (preg_match($this->pattern, $line) === 1) {
                return $this->weight;
            }
        }
        return false;
    }
}
