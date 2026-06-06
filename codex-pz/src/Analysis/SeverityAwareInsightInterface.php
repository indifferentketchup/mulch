<?php

namespace IndifferentKetchup\CodexPz\Analysis;

interface SeverityAwareInsightInterface extends InsightInterface
{
    public function getSeverity(): Severity;
}
