<?php

namespace IndifferentKetchup\CodexPz\Analyser;

use IndifferentKetchup\CodexPz\Log\AnalysableLogInterface;

/**
 * Class Analyser
 *
 * @package IndifferentKetchup\CodexPz\Analyser
 */
abstract class Analyser implements AnalyserInterface
{
    protected ?AnalysableLogInterface $log = null;

    /**
     * Set the log
     *
     * @param AnalysableLogInterface $log
     * @return $this
     */
    public function setLog(AnalysableLogInterface $log): static
    {
        $this->log = $log;
        return $this;
    }
}