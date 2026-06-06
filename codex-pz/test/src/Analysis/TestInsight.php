<?php

namespace IndifferentKetchup\CodexPz\Test\Src\Analysis;

use IndifferentKetchup\CodexPz\Analysis\Insight;
use IndifferentKetchup\CodexPz\Analysis\InsightInterface;

/**
 * Class TestInsight
 */
class TestInsight extends Insight
{
    /**
     * Get the insight as human-readable message
     *
     * @return string
     */
    public function getMessage(): string
    {
        return "This is a test insight";
    }

    /**
     * Check if the $insight object is equal with the current object
     *
     * @param InsightInterface $insight
     * @return bool
     */
    public function isEqual(InsightInterface $insight): bool
    {
        return false;
    }
}