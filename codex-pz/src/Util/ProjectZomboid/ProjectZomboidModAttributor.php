<?php

namespace IndifferentKetchup\CodexPz\Util\ProjectZomboid;

use IndifferentKetchup\CodexPz\Printer\Modification;
use IndifferentKetchup\CodexPz\Util\StackTraceEnricherInterface;

/**
 * Decorate `Lua((MOD:NAME))` mod-attribution tokens in PZ log lines (typically
 * appearing in KahluaThread.flushErrorMessage stack-trace blocks) with HTML
 * spans carrying the Steam Workshop ID where known.
 *
 * Output shape:
 *   - Known mod: `Lua((MOD:<span class="mod-attribution" data-workshop-id="2857548524">ImmersiveSolarArrays</span>)).foo(bar.lua:12)`
 *   - Unknown mod: `Lua((MOD:<span class="mod-attribution">SomeMod</span>)).foo(bar.lua:12)`
 *
 * Idempotent: re-running modify() over already-decorated text yields the
 * same output. The regex anchors on `MOD:` immediately followed by a
 * non-`)` name run, then a positive look-ahead for `)`. Decorated text
 * has the shape `MOD:<span ...>NAME</span>)`, so the captured run after
 * `MOD:` would begin with `<span ...>NAME</span` — but that run is then
 * required to terminate at the look-ahead `)`, which sits AFTER the
 * `</span>`. The captured group would therefore be `<span ...>NAME</span`
 * and after substitution we'd get `MOD:<span class="mod-attribution">&lt;span ...&gt;NAME&lt;/span</span>)`.
 * To prevent that double-wrap, the regex additionally requires that the
 * captured name not contain `<` (which would only ever appear in already-
 * decorated text).
 *
 * Wears two hats: extends Modification (so the iblogs Printer can plug it
 * into its modification pipeline) and implements StackTraceEnricherInterface
 * (typed marker for any stack-trace-only consumer).
 *
 * @package IndifferentKetchup\CodexPz\Util\ProjectZomboid
 */
class ProjectZomboidModAttributor extends Modification implements StackTraceEnricherInterface
{
    /**
     * Static mapping of mod names (as they appear in `MOD:<NAME>`) to
     * Steam Workshop IDs, harvested from `Loading: steamapps/.../108600/<id>/mods/<name>/`
     * paths in production logs. The map is intentionally small for this
     * release; future Phase 2 work can broaden it via JSON file or live
     * mining.
     */
    protected const array MOD_NAME_TO_WORKSHOP_ID = [
        'GaelGunStore-Firearms' => '3616176188',
        'ImmersiveSolarArrays' => '2857548524',
        'WaterGoesBad' => '2849467715',
        'WaterPipes' => '3118159023',
    ];

    /**
     * Regex matching the `MOD:<NAME>)` token. Captures NAME, with the
     * trailing `)` as a positive look-ahead so the close-paren stays in
     * the post-substitution text.
     *
     * NAME is a non-greedy run of any character except `)` and `<`. The
     * `<` exclusion is the idempotence guard: already-decorated text
     * `MOD:<span ...>NAME</span>)` would otherwise re-match because the
     * non-greedy run `[^)]+?` can swallow the `<span ...>NAME</span` prefix.
     * Excluding `<` makes the regex a no-op on decorated input.
     *
     * NAME may contain spaces, apostrophes, hyphens, dots, ampersands, and
     * other identifier-friendly characters seen in the wild
     * (`Spongie's Clothing`, `[B42] Bag Upgrade Plus`, etc.).
     */
    protected const string MOD_TOKEN_REGEX = '/MOD:([^)<]+?)(?=\))/u';

    /**
     * @inheritDoc
     */
    public function modify(string $text): string
    {
        return $this->decorate($text);
    }

    /**
     * @inheritDoc
     */
    public function enrich(string $rawTrace): string
    {
        return $this->decorate($rawTrace);
    }

    /**
     * Wrap every `MOD:NAME)` token in $text with a `<span class="mod-attribution">`
     * carrying a `data-workshop-id` attribute when the name is in the static
     * map. Mod names are HTML-escaped before insertion.
     */
    private function decorate(string $text): string
    {
        return preg_replace_callback(
            self::MOD_TOKEN_REGEX,
            static function (array $m): string {
                $rawName = $m[1];
                $safeName = htmlspecialchars($rawName, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $id = self::MOD_NAME_TO_WORKSHOP_ID[$rawName] ?? null;
                $idAttr = $id !== null
                    ? ' data-workshop-id="' . htmlspecialchars($id, ENT_QUOTES | ENT_HTML5, 'UTF-8') . '"'
                    : '';
                return 'MOD:<span class="mod-attribution"' . $idAttr . '>' . $safeName . '</span>';
            },
            $text
        ) ?? $text;
    }
}
