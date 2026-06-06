<?php

namespace IndifferentKetchup\CodexPz\Analysis;

enum AttributionConfidence: string
{
    /** Mod marker on the entry itself */
    case Direct = 'direct';
    /** Within lookback window */
    case Inferred = 'inferred';
    case Unknown = 'unknown';
}
