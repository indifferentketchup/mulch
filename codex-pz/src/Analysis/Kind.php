<?php

namespace IndifferentKetchup\CodexPz\Analysis;

enum Kind: string
{
    case LuaRuntime = 'lua_runtime';
    case RequireFailed = 'require_failed';
    case JavaException = 'java_exception';
    case Runtime = 'runtime';
    case EngineNoise = 'engine_noise';
    case MissingMod = 'missing_mod';
    case ModConflict = 'mod_conflict';
    case Unknown = 'unknown';
}
