<?php

namespace IndifferentKetchup\Iblogs\Frontend\Settings;

enum Setting: string
{
    case FULL_WIDTH = "fullWidth";
    case NO_WRAP = "noWrap";
    case FLOATING_SCROLLBAR = "floatingScrollbar";
    case OVERFLOW = "overflow";
    case HIDE_ENGINE_NOISE = "hideEngineNoise";
    case SHOW_ALL_ENTRIES = "showAllEntries";


    /**
     * @return string
     */
    function getLabel(): string
    {
        return match ($this) {
            Setting::FULL_WIDTH => "Full Width",
            Setting::NO_WRAP => "No Wrap",
            Setting::FLOATING_SCROLLBAR => "Floating Scrollbar",
            Setting::OVERFLOW => "Overflow",
            Setting::HIDE_ENGINE_NOISE => "Hide Engine Noise",
            Setting::SHOW_ALL_ENTRIES => "Show All Entries"
        };
    }

    /**
     * @return string
     */
    function getGroup(): string
    {
        return match ($this) {
            Setting::FULL_WIDTH,
            Setting::NO_WRAP,
            Setting::FLOATING_SCROLLBAR,
            Setting::OVERFLOW => "Layout",
            Setting::HIDE_ENGINE_NOISE,
            Setting::SHOW_ALL_ENTRIES => "Content"
        };
    }

    /**
     * @return string
     */
    function getDescription(): string
    {
        return match ($this) {
            Setting::FULL_WIDTH => "Remove the centered container to use the full viewport width.",
            Setting::NO_WRAP => "Disable line wrapping to show each log line as a single horizontal row.",
            Setting::FLOATING_SCROLLBAR => "Show a fixed bottom scrollbar for navigating wide log files.",
            Setting::OVERFLOW => "Allow the log container to overflow the page width.",
            Setting::HIDE_ENGINE_NOISE => "Filter out low-severity debug and engine noise from the problem panel.",
            Setting::SHOW_ALL_ENTRIES => "Disable smart folding to show all log entries, including info and mod-loads."
        };
    }

    /**
     * @return string|null
     */
    function getBodyClass(): ?string
    {
        return match ($this) {
            Setting::FULL_WIDTH => "setting-full-width",
            Setting::NO_WRAP => "setting-no-wrap",
            Setting::FLOATING_SCROLLBAR => "setting-floating-scrollbar",
            Setting::OVERFLOW => "setting-overflow",
            Setting::HIDE_ENGINE_NOISE => "setting-hide-engine-noise",
            default => null
        };
    }

    /**
     * @return bool
     */
    function getDefault(): bool
    {
        return match ($this) {
            Setting::HIDE_ENGINE_NOISE => true,
            default => false
        };
    }
}
