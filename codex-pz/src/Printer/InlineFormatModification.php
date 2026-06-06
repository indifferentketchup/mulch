<?php

namespace IndifferentKetchup\CodexPz\Printer;

/**
 * Abstract printer modification that translates inline format markers in
 * raw log text into HTML spans.
 *
 * Per-game subclasses implement modify() directly (inherited from
 * ModificationInterface). They translate game-specific format markers:
 *   - Minecraft: ANSI escape codes (`\e[0;31;22m`) emitted by the server's
 *     terminal logger.
 *   - Project Zomboid (planned): chat-channel prefix tokens like `[General]`.
 *
 * The class exists primarily as a typed marker so consumers (e.g. the iblogs
 * Printer) can dispatch a per-game InlineFormat when one is registered, and
 * skip it otherwise — without needing to know each concrete subclass.
 *
 * @package IndifferentKetchup\CodexPz\Printer
 */
abstract class InlineFormatModification extends Modification
{
}
