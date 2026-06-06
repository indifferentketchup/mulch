<?php

namespace Aternos\Codex\Hytale\Analysis\Information\Client;

use Aternos\Codex\Hytale\Analysis\Information\HytaleVersionInformation;
use Aternos\Codex\Hytale\Log\HytaleClientLog;
use Aternos\Codex\Hytale\Log\HytaleServerLog;

class HytaleClientVersionInformation extends HytaleVersionInformation
{
    /**
     * @return array|string[]
     */
    public static function getPatterns(): array
    {
        return [
            HytaleClientLog::getPattern('HytaleClient\.Application\.Program\|HytaleClient (v[\w\.\-]+)')
        ];
    }
}
