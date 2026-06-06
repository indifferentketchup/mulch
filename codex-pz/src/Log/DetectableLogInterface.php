<?php

namespace IndifferentKetchup\CodexPz\Log;

use IndifferentKetchup\CodexPz\Detective\DetectorInterface;

/**
 * Interface DetectableLogInterface
 *
 * @package IndifferentKetchup\CodexPz\Log
 */
interface DetectableLogInterface extends LogInterface
{
    /**
     * Get an array of detectors matching DetectorInterface
     *
     * @return DetectorInterface[]
     */
    public static function getDetectors(): array;
}