<?php

namespace Aternos\Codex\Hytale\Analyser;

use Aternos\Codex\Analyser\PatternAnalyser;
use Aternos\Codex\Hytale\Analysis\Information\Client\HytaleClientVersionInformation;

class HytaleClientAnalyser extends PatternAnalyser
{
    public function __construct()
    {
        $this->addPossibleInsightClass(HytaleClientVersionInformation::class);
    }
}
