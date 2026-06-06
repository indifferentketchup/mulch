<?php

namespace IndifferentKetchup\CodexPz\Analysis;

interface ModAttributedInsightInterface extends InsightInterface
{
    public function getModAttribution(): ?ModAttribution;
}
