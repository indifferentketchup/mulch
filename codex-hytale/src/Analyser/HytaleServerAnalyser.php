<?php

namespace Aternos\Codex\Hytale\Analyser;

use Aternos\Codex\Analyser\PatternAnalyser;
use Aternos\Codex\Hytale\Analysis\Information\Server\HytaleServerVersionInformation;

class HytaleServerAnalyser extends PatternAnalyser
{
    public function __construct()
    {
        $this->addPossibleInsightClass(HytaleServerVersionInformation::class);
    }
}
