<?php

namespace IndifferentKetchup\Iblogs\Data;

use IndifferentKetchup\CodexPz\Log\LogInterface;

/**
 * Stub for v1 — `mclogs` used `aternos/sherlock` plus the Vanilla / Fabric
 * Minecraft codex log subclasses to deobfuscate Mojang and Yarn mappings
 * in stack traces. iblogs targets Project Zomboid, which uses no such
 * mapping scheme, so the deobfuscator is a no-op until iblogs gains
 * Minecraft support.
 *
 * Restore the original mapping flow (Sherlock map locators + obfuscation
 * maps) when re-introducing Minecraft logs. The historical implementation
 * lives in mclogs upstream commits prior to the iblogs fork.
 */
class Deobfuscator
{
    public function __construct(protected LogInterface $codexLog)
    {
    }

    public function deobfuscate(): ?string
    {
        return null;
    }
}
