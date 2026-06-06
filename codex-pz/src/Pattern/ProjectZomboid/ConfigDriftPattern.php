<?php

namespace IndifferentKetchup\CodexPz\Pattern\ProjectZomboid;

/**
 * Analyser extractor regexes for server-config drift warnings in DebugLog-server.txt.
 *
 * Named groups are safe here (analyser patterns only — never passed to PatternParser).
 * Neither pattern matches entries containing "Exception thrown".
 *
 * Families:
 *   UNKNOWN_SANDBOX_OPTION — ERROR unknown SandboxOption "OptionName"
 *   UNKNOWN_ITEM_PARAM     — adding unknown item param "ParamName" = "value"
 */
class ConfigDriftPattern
{
    public const string UNKNOWN_SANDBOX_OPTION = '/ERROR unknown SandboxOption "(?<option>[^"]+)"/';

    public const string UNKNOWN_ITEM_PARAM = '/adding unknown item param "(?<param>[^"]+)"/';
}
