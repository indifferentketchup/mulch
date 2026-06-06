<?php

namespace Aternos\Codex\Hytale\Analysis\Information\Server;

use Aternos\Codex\Hytale\Analysis\Information\HytaleVersionInformation;
use Aternos\Codex\Hytale\Log\HytaleServerLog;

class HytaleServerVersionInformation extends HytaleVersionInformation
{
    /**
     * @return array|string[]
     */
    public static function getPatterns(): array
    {
        return [
            HytaleServerLog::getPattern('\[HytaleServer\] Booting up HytaleServer - Version: ([\w\.\-]+), Revision: \w+')
        ];
    }
}
