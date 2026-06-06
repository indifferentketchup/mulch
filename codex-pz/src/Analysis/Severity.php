<?php

namespace IndifferentKetchup\CodexPz\Analysis;

enum Severity: int
{
    /** Engine chatter (DebugFileWatcher, Kahlua dumps) — must not outrank real crashes */
    case Noise = 5;
    /** Low-frequency recoverable warnings */
    case Low = 20;
    /** Mod warnings, cross-mod conflicts */
    case Medium = 50;
    /** Mod crashes, server-tick exceptions */
    case High = 80;
    /** Parse failures, fatal exceptions */
    case Critical = 100;
}
