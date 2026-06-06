<?php

namespace IndifferentKetchup\CodexPz\Pattern\ProjectZomboid;

/**
 * Analyser extractor regexes for asset/resource warnings in DebugLog-server.txt.
 *
 * Named groups are safe here (analyser patterns only — never passed to PatternParser).
 * None of these patterns match entries containing "Exception thrown".
 *
 * Families:
 *   SPRITE_CONFIG_INVALID — SpriteConfig.initObjectInfo > Invalid SpriteConfig object! scripted object = Name
 *   MISSING_ICON          — XuiSkin$EntityUiStyle.LoadComponentInfo > Could not find icon: Name
 *   MISSING_THUMPSOUND    — BrokenFences.addBrokenTiles > Missing ThumpSound for breakable object TileId
 *   BUFFER_OVERFLOW       — IsoChunk.Save: BufferOverflowException, growing ByteBuffer
 */
class AssetWarningPattern
{
    public const string SPRITE_CONFIG_INVALID = '/Invalid SpriteConfig object! scripted object = (?<object>[^\n.]+)/';

    public const string MISSING_ICON = '/Could not find icon: (?<icon>[^\n.]+)/';

    public const string MISSING_THUMPSOUND = '/Missing ThumpSound for breakable object (?<tile>[^\n.]+)/';

    public const string BUFFER_OVERFLOW = '/IsoChunk\.Save: BufferOverflowException, growing ByteBuffer/';
}
