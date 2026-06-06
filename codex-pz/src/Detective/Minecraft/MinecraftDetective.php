<?php

namespace IndifferentKetchup\CodexPz\Detective\Minecraft;

use IndifferentKetchup\CodexPz\Detective\Detective;
use IndifferentKetchup\CodexPz\Log\Minecraft\Vanilla\VanillaServerLog;

/**
 * Pre-registers the Minecraft log classes ik-codex currently supports
 * so that detect() can dispatch among them on first-line content signature.
 *
 * Phase 1 wires only VanillaServerLog. Phase 2 will register the other
 * 30+ variants from the upstream package (see TODO below).
 */
class MinecraftDetective extends Detective
{
    public function __construct()
    {
        // TODO Phase 2: register Fabric/Quilt/Forge/NeoForge/Bukkit/Spigot/Paper/
        // Purpur/Folia/Mohist/Magma/Arclight/BungeeCord/Velocity/Geyser/Waterfall/
        // Bedrock/Pocketmine + crash-report + launcher-client log variants.
        $this->addPossibleLogClass(VanillaServerLog::class);
    }
}
