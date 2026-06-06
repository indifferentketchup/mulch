<?php

namespace IndifferentKetchup\CodexPz\Analysis;

use JsonSerializable;

/**
 * Interface SolutionInterface
 *
 * @package IndifferentKetchup\CodexPz\Analysis
 */
interface SolutionInterface extends JsonSerializable
{
    /**
     * Get the solution as a human-readable message
     *
     * @return string
     */
    public function getMessage(): string;
    public function __toString(): string;
}