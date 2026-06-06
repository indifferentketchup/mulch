<?php

namespace IndifferentKetchup\CodexPz\Pattern\ProjectZomboid;

/**
 * Analyser extractor regexes for animation/skeleton warnings in DebugLog-server.txt.
 *
 * Named groups are safe here (analyser patterns only — never passed to PatternParser).
 * Neither pattern matches entries containing "Exception thrown".
 *
 * Families:
 *   ANIM_CLIP_NOT_FOUND  — AnimationPlayer.play > Anim Clip not found: Name
 *   BONE_INDEX_MISSING   — ImportedSkeleton.collectBoneFrames > Could not find bone index for node name: "Name"
 */
class AnimationWarningPattern
{
    public const string ANIM_CLIP_NOT_FOUND = '/Anim Clip not found: (?<clip>[^\n.]+)/';

    public const string BONE_INDEX_MISSING = '/Could not find bone index for node name: "(?<node>[^"]+)"/';
}
