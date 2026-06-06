<?php

namespace IndifferentKetchup\CodexPz\Printer\Minecraft;

use IndifferentKetchup\CodexPz\Printer\InlineFormatModification;

/**
 * Translate Minecraft server's ANSI escape codes (e.g. \e[0;31;22m for red)
 * into HTML spans with `format-<name>` classes for downstream styling.
 *
 * Whole-text model: a single open span at byte N stays open until either a
 * later code replaces it OR end-of-text, at which point N closing `</span>`
 * are appended. This mirrors the upstream `aternos/codex-minecraft`
 * FormatModification behaviour exactly.
 *
 * @package IndifferentKetchup\CodexPz\Printer\Minecraft
 */
class MinecraftInlineFormat extends InlineFormatModification
{
    /**
     * ANSI code suffix → format-name mapping. Each key is the part of an
     * ANSI sequence that follows `\e[`. Each value is a logical format
     * name that matches the iblogs CSS class `.format-<name>` rules
     * already in iblogs.css.
     */
    protected const array FORMAT_CODES = [
        "0;30;22m" => "black",
        "0;34;22m" => "darkblue",
        "0;32;22m" => "darkgreen",
        "0;36;22m" => "darkaqua",
        "0;31;22m" => "darkred",
        "0;35;22m" => "darkpurple",
        "0;33;22m" => "gold",
        "0;37;22m" => "gray",
        "0;30;1m" => "darkgray",
        "0;34;1m" => "blue",
        "0;32;1m" => "green",
        "0;36;1m" => "aqua",
        "0;31;1m" => "red",
        "0;35;1m" => "lightpurple",
        "0;33;1m" => "yellow",
        "0;37;1m" => "white",
        "21m" => "bold",
        "4m" => "underline",
        "3m" => "italic",
        "9m" => "strike",
        "5m" => "magic",
        "m" => "reset",
    ];

    /**
     * Translate every ANSI escape code in $text to a `<span class="format-X">`,
     * then append one closing `</span>` for each replacement so any unclosed
     * spans at end-of-text are balanced.
     *
     * @param string $text
     * @return string
     */
    public function modify(string $text): string
    {
        $search = [];
        $replace = [];
        foreach (self::FORMAT_CODES as $code => $format) {
            $search[] = "\e[" . $code;
            $replace[] = '<span class="format-' . $format . '">';
        }

        $count = 0;
        $text = str_replace($search, $replace, $text, $count);
        $text .= str_repeat('</span>', $count);

        return $text;
    }
}
