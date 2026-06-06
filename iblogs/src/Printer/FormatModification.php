<?php

namespace IndifferentKetchup\Iblogs\Printer;

use IndifferentKetchup\CodexPz\Printer\Modification;

/**
 * Per-game `Modification` dispatcher.
 *
 * `mclogs` originally extended `Aternos\Codex\Minecraft\Printer\FormatModification`
 * directly to translate Minecraft section-sign format codes into HTML format
 * spans (`format-black`, `format-darkblue`, etc., backed by the format-colors
 * block in the iblogs CSS). With multi-game support, this slot now delegates
 * to whatever per-game `Modification` is appropriate for the wrapped log,
 * picked at log-set time (see {@see Printer::setLog()}). The delegate may be:
 *
 * - a format-code translator (Minecraft `§`-code → HTML spans), or
 * - a stack-frame enricher (Project Zomboid Lua mod-name attribution), or
 * - any future per-game `Modification` that maps an entry-line string to a
 *   decorated string with the same semantics.
 *
 * When the wrapped log has no game-specific Modification (Hytale at present),
 * the delegate is `null` and `modify()` is a pass-through. The technical
 * contract is unchanged from the Minecraft-only era: a single delegate,
 * string-in / string-out, idempotent on already-decorated input.
 *
 * The CSS `format-*` color classes are retained so any future game-specific
 * format-code scheme can reuse the existing styling.
 */
class FormatModification extends Modification
{
    public function __construct(
        private ?Modification $delegate = null,
    ) {
    }

    public function setDelegate(?Modification $delegate): static
    {
        $this->delegate = $delegate;
        return $this;
    }

    public function modify(string $text): string
    {
        return $this->delegate?->modify($text) ?? $text;
    }
}
