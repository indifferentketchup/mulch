<?php

namespace IndifferentKetchup\CodexPz\Analysis;

interface CauseChainInsightInterface extends InsightInterface
{
    public function getCauseChain(): ?string;
}
