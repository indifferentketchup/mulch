<?php

namespace IndifferentKetchup\CodexPz\Analysis;

enum Severity: int
{
    /** Base severity floor for engine chatter (DebugFileWatcher, Kahlua dumps); must not outrank real crashes */
    case Noise = 5;
    /** Base severity floor for low-frequency recoverable warnings */
    case Low = 20;
    /** Base severity floor for mod warnings, cross-mod conflicts */
    case Medium = 50;
    /** Base severity floor for mod crashes, server-tick exceptions */
    case High = 80;
    /** Base severity floor for parse failures, fatal exceptions */
    case Critical = 100;
}
