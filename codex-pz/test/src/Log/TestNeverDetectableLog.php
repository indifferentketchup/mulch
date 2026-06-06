<?php

namespace IndifferentKetchup\CodexPz\Test\Src\Log;

use IndifferentKetchup\CodexPz\Detective\DetectorInterface;
use IndifferentKetchup\CodexPz\Detective\SinglePatternDetector;
use IndifferentKetchup\CodexPz\Log\DetectableLogInterface;
use IndifferentKetchup\CodexPz\Log\Log;

/**
 * Class TestNeverDetectableLog
 */
class TestNeverDetectableLog extends Log implements DetectableLogInterface
{
    /**
     * Get an array of detectors matching DetectorInterface
     *
     * @return DetectorInterface[]
     */
    public static function getDetectors(): array
    {
        return [(new SinglePatternDetector())->setPattern('/missing/')];
    }
}