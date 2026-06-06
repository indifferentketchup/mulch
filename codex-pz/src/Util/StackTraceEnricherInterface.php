<?php

namespace IndifferentKetchup\CodexPz\Util;

/**
 * Render-time decoration of a raw stack trace.
 *
 * Per-game implementations enrich traces differently:
 *   - Minecraft (planned): resolve obfuscated symbols against Mojang/Yarn maps.
 *   - Project Zomboid (planned): attach mod metadata + Workshop links to
 *     `Lua((MOD:NAME)).func(file:N)` frames.
 *
 * Implementations are string-in / string-out so they slot cleanly between the
 * Codex `Printer` and any consumer (HTML, plain text, JSON export).
 *
 * @package IndifferentKetchup\CodexPz\Util
 */
interface StackTraceEnricherInterface
{
    /**
     * Decorate $rawTrace with whatever annotation this game's enrichment provides.
     * Implementations must return safe, well-formed output for the consumer's
     * intended sink (HTML escaping, link wrapping, etc.).
     *
     * @param string $rawTrace
     * @return string
     */
    public function enrich(string $rawTrace): string;
}
