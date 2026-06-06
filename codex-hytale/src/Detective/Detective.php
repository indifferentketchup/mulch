<?php

namespace Aternos\Codex\Hytale\Detective;

class Detective extends \Aternos\Codex\Detective\Detective
{
    protected array $possibleLogClasses = [
        \Aternos\Codex\Hytale\Log\HytaleServerLog::class,
        \Aternos\Codex\Hytale\Log\HytaleClientLog::class,
    ];
}
