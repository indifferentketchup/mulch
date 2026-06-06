<?php

namespace IndifferentKetchup\CodexPz\Detective;

/**
 * Match a regex against the source path of the log file
 *
 * Returns a configured weight when the LogFileInterface exposes a non-null
 * path that matches $pattern. Returns false when no path is known
 * (StringLogFile, StreamLogFile) or when the pattern does not match. Pattern
 * is compared against the full path; anchor with $ for strict suffix matching.
 *
 * @package IndifferentKetchup\CodexPz\Detective
 */
class FilenameDetector extends Detector
{
    protected ?string $pattern = null;
    protected float $weight = 0.95;

    public function setPattern(string $pattern): static
    {
        $this->pattern = $pattern;
        return $this;
    }

    public function setWeight(float $weight): static
    {
        $this->weight = $weight;
        return $this;
    }

    public function detect(): bool|float
    {
        $path = $this->logFile->getPath();
        if ($path === null || $this->pattern === null) {
            return false;
        }

        if (preg_match($this->pattern, $path) === 1) {
            return $this->weight;
        }

        return false;
    }
}
