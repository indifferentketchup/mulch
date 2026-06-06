<?php

namespace IndifferentKetchup\CodexPz\Analysis;

/**
 * Marker interface for known-benign engine chatter.
 * Consumers filter these from the default problem count.
 */
interface EngineNoiseInsightInterface extends InsightInterface
{
}
