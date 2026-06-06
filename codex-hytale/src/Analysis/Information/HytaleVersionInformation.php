<?php

namespace Aternos\Codex\Hytale\Analysis\Information;

abstract class HytaleVersionInformation extends HytaleInformation
{
    /**
     * @return string
     */
    public function getLabel(): string
    {
        return "Hytale Version";
    }
}
